		/*****************************************************
		======================================================

						  Yoga15 App Socket Server
							by JacksProject.DEV

		======================================================

		*****************************************************/

const sql = require('mysql');
const sha1 = require('sha1');
const fs = require('fs');
const request = require('request');
const PORT = 2053;
const clients = [];
const sendExpoNotification = require('./includes/notify');
const Y15Photo = require('./includes/photo');
const Badges = require('./includes/badges');
const remindersSent = {};
const wpPosts = {};

const db_config = {
	host: 'yoga15.com',
	user: 'yogaform_wp670',
	password: 'p2.87SRm0]',
	database: 'dbn1gl2htibvj5'
}

var mysql = null;

process.on('uncaughtException', (err) => {
	err = String(err);
	console.log('EXEPTION: ' + err);
	
	fs.appendFileSync('error.log', err);
	fs.appendFileSync('error.log', '\n\n');
});

function handleDisconnect() {
	if(mysql) 
		mysql.end();
	
	mysql = sql.createPool(db_config);
	
	mysql.on('error', function(err) {
		console.log('Database error: ' + err, 'ERROR');
		setTimeout(handleDisconnect, 2000);
	});
}
function connectDatabase() {
	handleDisconnect();
}
function StartServer() {
	var https = require('https');
	var express = require('express');
	var privateKey = fs.readFileSync('../ssl/jack.key').toString();
	var certificate = fs.readFileSync('../ssl/jack.crt').toString();
	
	var server = https.createServer({key: privateKey, cert: certificate});
	
	io = require('socket.io')(server, {cors: {
		origin: '*',
	}});
	
	io.on('connection', (client) => {
		console.log(`New connection from ${client.handshake.address}!`);
		
		client.error = (msg) => {
			client.emit('error', msg);
		}
		const authenticate = () => {
			if(!client.data) {
				client.error('Not authenticated to server');
				return false;
			}
			return true;
		}
		
		/*
			App handlers
		*/
		client.on('login', (sessionkey, pushtoken) => {
			client.emit('badges', Badges);
			
			if(clients.indexOf(client) > -1)
				return;
			
			mysql.query('SELECT * FROM users u JOIN yogaform_wp670.wpaa_users d ON d.ID = u.id WHERE u.id = (SELECT `mpid` FROM sessions WHERE `key` = ?)', [sessionkey], (error, rows) => {
				const data = rows?.[0];
				
				if(!data) {
					console.log('Auth error: ', sessionkey);
					return client.error('Could not authenticate to server');
				}
				
				client.data = processUserData(data);
				client.unreadMessages = 0;
				client.cprogs = {};
				client.sessionkey = sessionkey;
				
				clients.push(client);
				
				// Store their unreads and sync them
				mysql.query('SELECT count(*) as count FROM `messages` WHERE `read` = 0 AND `recepient` = ?', [client.data.id], (error, rows) => {
					client.unreadMessages = rows?.[0]?.['count'] ?? 0;
					client.emit('unreads', client.unreadMessages);
				});
				
				// Update their muted ids
				if(!client.mutedIds)
					client.mutedIds = [];
				
				mysql.query("SELECT * FROM muted WHERE `mpid` = ?", [client.data.id], (error, rows) => {
					if(error) 
						return;
					
					rows.forEach(row => {
						if(!client.mutedIds.includes(row.mutedId))
							client.mutedIds.push(row.mutedId);
					});
				});
				
				console.log('Authenticated to ' + client.data.username);
				
				// Ensure the tokens are updated
				if(pushtoken) {
					mysql.query('SELECT * FROM pushtokens WHERE `mpid` = ? AND `token` = ?', [client.data.id, pushtoken], (error, rows) => {
						if(rows?.length > 0)
							return;
						
						mysql.query('INSERT INTO pushtokens (`mpid`, `token`) VALUES (?, ?)', [client.data.id, pushtoken]);
					});
				}else{
					console.log('No push token');
				}
				
				client.emit('login', {success: true, username: client.data.username});
				
				checkIsSubscriber(client);
				updateCprog(client);
				
				sendVideoWatchHistory(client)
				.then(() => sendStats(client))
				.catch((error) => console.log('Post login error:', error));
			});
		});
		client.on('message', (contactId, message) => {
			if(!authenticate()) return;
			
			broadcastToID(contactId, 'message', client.data.id, message);
			
			if(handleCommand(message, client) === true)
				return;
			
			if(contactId == 2) {
				// Notify bot
				
				const parts = message.split('\n');
				const title = parts.shift();
				const body = parts.join('\n');
				
				setTimeout(() => notifyAll(title, body), 5000);
			}
			
			let unreads = -1;
			
			clients.forEach((tclient) => {
				if(tclient?.data?.id == contactId) {
					tclient.unreadMessages++;
					unreads = tclient.unreadMessages;
					tclient.emit('unreads', tclient.unreadMessages);
				}
			})
			
			// Check if muted first
			mysql.query('SELECT * FROM muted WHERE `mpid` = ? AND `mutedId` = ?', [contactId, client.data.id], (error, rows) => {
				if(rows?.length > 0)
					return;
				
				notify(contactId, client.data.displayName, message.substr(0, 128), {
					contactId: client.data.id,
					type: 'message',
					unreads: unreads
				});
			});
		});
		client.on('reminders', (date, days) => {
			client.data.reminderTime = date;
			client.data.reminderDays = days;
			
			mysql.query('UPDATE users SET `reminderTime` = ?, `reminderDays` = ? WHERE `id` = ?', [date, days.join(','), client.data.id]);
		});
		client.on('intro-update', (account) => {
			client.data.experience = account.experience;
			client.data.preferredExperience = account.preferredExperience;
			client.data.sports = account.sports.join(',');
			
			mysql.query('UPDATE users SET `experience` = ?, `preferredExperience` = ?, `sports` = ?, `introCompleted` = 1 WHERE `id` = ?', [client.data.experience, client.data.preferredExperience, client.data.sports, client.data.id]);
		});
		client.on('disconnect-strava', () => {
			client.data.stravatoken = null;
			client.data.stravaexpire = null;
			client.data.stravarefresh = null;
			client.data.stravauser = null;
			
			mysql.query('UPDATE users SET `stravatoken` = NULL, `stravaexpire` = NULL, `stravarefresh` = NULL, `stravauser` = NULL WHERE `id` = ?', [client.data.id]);
		
			client.emit('stravatoken', null, null, null, null);
			client.emit('socialmedias', getSocialMedias(client));
		});
		client.on('probe-socials', () => {
			client.emit('socialmedias', getSocialMedias(client));
		});
		client.on('watched', (postId, delta, seconds) => {
			const onFinish = (error) => {
				!error && sendVideoWatchHistory(client).then(() => sendStats(client));
			}
			
			mysql.query('SELECT * FROM videohistory WHERE `mpid` = ? AND `post` = ?', [client.data.id, postId], (error, rows) => {
				if(rows?.length < 1)
					mysql.query('INSERT INTO videohistory (`mpid`, `post`, `delta`, `seconds`, `time`) VALUES (?, ?, ?, ?, ?)', [client.data.id, postId, delta, seconds, time()], onFinish);
				else
					mysql.query('UPDATE videohistory SET `delta` = ?, `seconds` = ?, `time` = ? WHERE `mpid` = ? AND `post` = ?', [delta, seconds, time(), client.data.id, postId], onFinish);
				
			});
		});
		client.on('notification-settings', (settings) => {
			if(!settings)
				return;
			
			client.data.allowNotifyBot = settings.allowNotifyBot;
			client.data.allowNotifyContent = settings.allowNotifyContent;
			client.data.allowNotifyRewards = settings.allowNotifyRewards;
			
			mysql.query('UPDATE users SET `allowNotifyBot` = ?, `allowNotifyContent` = ?, `allowNotifyRewards` = ? WHERE `id` = ?', [client.data.allowNotifyBot, client.data.allowNotifyContent, client.data.allowNotifyRewards, client.data.id]);
		});
		client.on('save-profile', (firstName, lastName) => {
			client.data.firstName = firstName;
			client.data.lastName = lastName;
			
			mysql.query('UPDATE users SET `firstName` = ?, `lastName` = ? WHERE `id` = ?', [firstName, lastName, client.data.id]);
		});
		client.on('save-photo', (base64imagedata) => {
			Y15Photo.savePhoto(base64imagedata, client.data.id)
			.then(fileName => {
				const url = `https://yoga15.jacksproject.dev/resources/${fileName}?${Date.now()}`;
				console.log(url);
				client.data.photo = url;
				
				mysql.query('UPDATE users SET `photo` = ? WHERE `id` = ?', [url, client.data.id]);
			})
			.catch(error => console.log(error));
		});
		client.on('mute', (mutedId) => {
			if(isNaN(mutedId))
				return;
				
			if(!client.mutedIds)
				client.mutedIds = [];
			
			mysql.query('SELECT * FROM `muted` WHERE `mpid` = ? AND `mutedId` = ?', [client.data.id, mutedId], (error, rows) => {
				if(error)
					return;
				
				if(rows.length > 0) {
					mysql.query('DELETE FROM `muted` WHERE `mpid` = ? AND `mutedId` = ?', [client.data.id, mutedId]);
					client.mutedIds.splice(client.mutedIds.indexOf(mutedId), 1);
				}else{
					mysql.query('INSERT INTO `muted` (`mpid`, `mutedId`) VALUES (?, ?)', [client.data.id, mutedId]);
					client.mutedIds.push(mutedId);
				}
			});
		});
		client.on('deletechat', (contactId) => {
			if(isNaN(contactId))
				return;
				
			//mysql.query("DELETE FROM `messages` WHERE (`recepient` = ? AND `sender` = ?) OR (`recepient` = ? AND `sender` = ?)", [client.data.id, contactId, contactId, client.data.id]);
			
			mysql.query('SELECT * FROM `hidecontacts` WHERE `mpid` = ? AND `contactId` = ?', [client.data.id, contactId], (error, rows) => {
				if(error)
					return;
				
				if(rows.length > 0) {
					mysql.query('DELETE FROM `hidecontacts` WHERE `mpid` = ? AND `contactId` = ?', [client.data.id, contactId]);
				}else{
					mysql.query('INSERT INTO `hidecontacts` (`mpid`, `contactId`) VALUES (?, ?)', [client.data.id, contactId]);
				}
			});
			
			client.emit('pong', 'deletechat', contactId);
		});
		client.on('ping', (name) => {
			client.emit('pong', name);
		});
		client.on('logout', (pushtoken) => {
			mysql.query('DELETE FROM pushtokens WHERE `token` = ? AND `mpid` = ?', [pushtoken, client.data.id]);
			mysql.query('DELETE FROM sessions WHERE `key` = ? AND `mpid` = ?', [client.sessionkey, client.data.id]);
		});
		client.on('delete-account', (pushtoken) => {
			mysql.query('DELETE FROM users WHERE `id` = ?', [client.data.id]);
			
			if(client.data.id == 19700)
				mysql.query('DELETE FROM `yogaform_wp670`.`wpaa_users` WHERE `ID` = ?', [client.data.id]);
		});
		
		/*
			Basic handlers
		*/
		client.on('error', (err) => {
			console.log('Socket error: ' + err);
		});
		client.on('disconnect', (err) => {
			if(clients.indexOf(client) > -1)
				clients.splice(clients.indexOf(client), 1);
		});
	});
	
	server.listen(PORT);
	
	connectDatabase();
	
	setInterval(updateStravaTokens, 5000);
	setInterval(updateGarminTokens, 5000);
	setInterval(updateReminders, 30000);
	setInterval(updateAchieve, 10000);
	setInterval(updatePosts, 60000);
	setInterval(updatePostsNotify, 60000);
	setInterval(updateCprogs, 60000);
	
	setTimeout(updateReminders, 5000);
	setTimeout(updatePosts, 5000);
	setTimeout(updatePostsNotify, 8000);
			
	console.log('=======================================');
	console.log('Yoga15 Socket Server');
	console.log('Started listening on port ' + PORT + '.');
	console.log('=======================================');
}

function getUTCDate() {
	return new Date(getUTCISOString(new Date()));
}
function getUTCISOString(date) {
	return date.toISOString().slice(0, 19) + 'Z';
}
function time() {
	return Math.floor(getUTCDate().getTime() / 1000);
}
function generateRandomString(length = 10) {
	const characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	const charactersLength = characters.length;
	let randomString = '';
	for (let i = 0; i < length; i++) {
		randomString += characters[Math.floor(Math.random() * charactersLength)];
	}
	return randomString;
}
function underscoreToCamel(string) {
	string = string.replace(/_/g, ' ');
	string = string.split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join('');
	string = string.charAt(0).toLowerCase() + string.slice(1);
	return string;
}
function getAccount(mp_user, app_user) {
	const account = { ...app_user };

	for (const k in mp_user) {
		if (mp_user.hasOwnProperty(k)) {
			const key = underscoreToCamel(k.replace("user_", ""));
			account[key] = mp_user[k];
		}
	}

	account["username"] = account["login"];
	account["legacyName"] = account["displayName"];
	account["favourites"] = getNumList(account["favourites"]);

	delete account["ID"];
	delete account["iD"];
	delete account["pass"];
	delete account["displayName"];
	delete account["alias"];
	delete account["nicename"];

	return account;
}
function getNumList(favouritesStr) {
	const favs = [];
	const expl = favouritesStr.split(",");

	for (const id of expl) {
		if (!isNaN(id) && id) {
			favs.push(Number(id));
		}
	}

	return favs;
}
function extractExcerpt(text, length = 100) {
	text = text.replace(extractVimeoVideoUrl(text), '');
	text = removeComments(text, 'wp:embed');
	text = removeBoxTags(text);
	text = stripHtmlComments(text);
	text = stripTags(text);
	text = decodeHtmlEntities(text);
	text = text.replace(/\r?\n/g, '');
	text = text.trim();

	let excerpt = text.substring(0, length);

	const lastSpace = excerpt.lastIndexOf(' ');
	if (lastSpace !== -1) {
		excerpt = excerpt.substring(0, lastSpace);
	}

	if (text.length > excerpt.length) {
		excerpt += '...';
	}

	return excerpt;
}
function stripHtmlComments(html) {
	const pattern = /<!--(.*?)-->/gs;
	const strippedHtml = html.replace(pattern, '');
	return strippedHtml;
}
function removeComments(html, tagName) {
	const pattern = new RegExp(`<!--\\s*${tagName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}.*?${tagName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\s*-->`, 'gs');
	const strippedHtml = html.replace(pattern, '');
	return strippedHtml;
}
function removeBoxTags(text) {
	const pattern = /\[[^\]]*\]/gi;
	const strippedText = text.replace(pattern, '');
	return strippedText;
}
function getLessonIds(stepsArr) {
	const lessons = Object.keys(stepsArr);
	return lessons;
}
function getCourseProgressData(rawCprogs) {
	const cprogs = {};

	for (const courseId in rawCprogs) {
		if (rawCprogs.hasOwnProperty(courseId)) {
			const progressObj = rawCprogs[courseId];
			const lessonsCompleted = [];

			for (const lessonId in progressObj.lessons) {
				if (progressObj.lessons.hasOwnProperty(lessonId) && progressObj.lessons[lessonId]) {
					lessonsCompleted.push(lessonId);
				}
			}

			cprogs[courseId] = lessonsCompleted;
		}
	}

	return cprogs;
}
function extractVimeoVideoUrl(text) {
	const pattern = /\bhttps?:\/\/(?:www\.)?vimeo\.com\/[a-zA-Z0-9\-]+\b/;
	const matches = text.match(pattern);

	if (matches && matches.length > 0) {
		return matches[0];
	}
}
function extractEmbedText(text) {
	let content = extractEmbedTextNew(text);

	if (!content) {
		content = extractEmbedTextLegacy(text);
	}

	return content;
}
function extractEmbedTextNew(content) {
	const pattern = /<!--\s*wp:embed[^>]+>(.*?)<!--\s*\/wp:embed\s*-->/gs;
	const matches = content.match(pattern);
	return (matches && matches.length > 0) ? matches[1] : '';
}
function extractEmbedTextLegacy(content) {
	const pattern = /<!--\s*wp:core-embed\/vimeo[^>]*>(.*?)<!--\s*\/wp:core-embed\/vimeo\s*-->/gs;
	const matches = content.match(pattern);
	return (matches && matches.length > 0) ? matches[1] : '';
}

function getVimeoURL(text) {
	return extractVimeoVideoUrl(text);
}

function stripEmbedContent(text) {
	const embedText = extractEmbedText(text);
	text = text.replace(embedText, '');
	return text;
}

function getMetaObject(metaRows) {
	const meta = {};
	
	metaRows.forEach(metaRow => {
		meta[metaRow['meta_key']] = metaRow['meta_value'];
	});
	
	return meta;
}

function processUserData(data) {
	data.username = data.user_login;
	data.displayName = getDisplayName(data);
	data.user_pass = null;
	data.reminderDays = (data.reminderDays || '').trim().split(',') ?? [];
	data.reminderDays = data.reminderDays.map(Number);
	data.pushtokens = (data.pushtokens || '').trim().split(',') ?? [];
	
	return data;
}
function getSocketById(id) {
	for(var i in clients) {
		if(clients[i]?.data?.id == id)
			return clients[i];
	}
}
function getSocketByName(name) {
	for(var i in clients) {
		if(clients[i]?.data?.username.toLowerCase() == name.toLowerCase())
			return clients[i];
	}
}
function broadcastToID(id, ...params) {
	clients.forEach((client) => {
		if(client?.data?.id == id) {
			client.emit(...params);
		}
	})
}
function updateAchieve() {
	clients.forEach(client => checkAchieve(client));
}
function updateStravaTokens() {
	clients.forEach((client) => {
		if(!client?.data)
			return;
		
		mysql.query('SELECT `stravatoken`,`stravarefresh`,`stravaexpire`,`stravauser` FROM users WHERE `id` = ?', [client.data.id], (e,r,f) => {
			const row = r?.[0];
			
			if(!row)
				return;
			
			if(client.data.stravatoken != row.stravatoken) {
				// The token has updated... send it to the client!
				
				console.log('Sending strava token to ' + client.data.username);
				
				client.emit('stravatoken', row.stravatoken, row.stravarefresh, row.stravaexpire, row.stravauser);
				
				client.data.stravatoken = row.stravatoken;
				client.data.stravarefresh = row.stravarefresh;
				client.data.stravaexpire = row.stravaexpire;
			}
		});
	});
}
function updateGarminTokens() {
	clients.forEach((client) => {
		if(!client?.data)
			return;
		
		mysql.query('SELECT `garminverifier` FROM users WHERE `id` = ?', [client.data.id], (e,r,f) => {
			const row = r?.[0];
			
			if(!row)
				return;
			
			if(client.data.garminverifier != row.garminverifier) {
				// The verifier has updated... send it to the client!
				
				console.log('Sending garmin verifier to ' + client.data.username);
				
				client.emit('garminverifier', row.garminverifier);
				
				client.data.garminverifier = row.garminverifier;
			}
		});
	});
}
function updateReminders() {
	const now = new Date(Date.now() - (3600 * 1000));
	
	mysql.query('SELECT * FROM users', (error, rows) => {
		rows.forEach(row => {
			if(!row.reminderDays || !row.reminderTime)
				return;
			
			const days = String(row.reminderDays).split(',').map(Number);
			const reminderTime = new Date(row.reminderTime);
			
			if(days.indexOf(now.getUTCDay()) < 0)
				return;
				
			const lastReminder = remindersSent[row.id];
				
			if(reminderTime.getUTCHours() == now.getUTCHours() && reminderTime.getMinutes() == now.getMinutes() && (now.getTime() - lastReminder?.getTime?.() > (70*1000) || !lastReminder)) {
				if(row.id == 19760) {
					console.log(reminderTime.getUTCHours(), now.getUTCHours(), reminderTime.getMinutes(), now.getMinutes());
				}
				
				// Send reminder!
				notify(row.id, 'Yoga 15 Reminder', 'Its time to do some yoga!', {type: 'videos'});
				
				remindersSent[row.id] = getUTCDate();
				
				console.log(`Sending reminder to ${row.id}`);
			}
		});
	});
}
function updatePosts() {
	const posts = [];
	
	const onFinished = () => {
		// Re-send video history data
		clients.forEach(client => sendVideoWatchHistory(client));
	}
	
	mysql.query("SELECT * FROM yogaform_wp670.wpaa_posts WHERE `post_status` = 'publish'", (error, rows) => {
		rows.forEach(row => {
			row.id = row.ID;
			wpPosts[row.ID] = row;
			posts.push(wpPosts[row.ID]);
		});
		
		mysql.query('SELECT * FROM `posts`', (error, rows) => {
			rows.forEach(row => {
				if(!wpPosts[row.postId])
					return;
					
				wpPosts[row.postId].video = true;
				
				for(let key in row) {
					let value = row[key];
					
					switch(key) {
						case 'tags':
						case 'categories':
						case 'meta':
							try {
								value = JSON.parse(value);
							}catch(e){};
						break;
					}
					
					if(key == 'id')
						continue;
					
					wpPosts[row.postId][key] = value;
				}
			});
			
			onFinished();
		});
	})
}
function updatePostsNotify() {
	mysql.query('SELECT * FROM postsnotified', (error, rows) => {
		const doneIds = [];
		
		rows.forEach(row => doneIds.push(row.postId));
		
		for(let i in wpPosts) {
			const post = wpPosts[i];
			
			if(!post?.video)
				continue;
			
			if(doneIds.indexOf(post.ID) > -1)
				continue;
			
			notifyNewPost(post);
		}
	})
}
function updateCprogs() {
	clients.forEach(client => updateCprog(client));
}
function updateCprog(client) {
	mysql.query("SELECT `course_id` AS `courseId`, `post_id` AS `lessonId`, `activity_completed` AS `completedAt`, `activity_updated` AS `updatedAt`, `activity_type` as `activityType` FROM yogaform_wp670.wpaa_learndash_user_activity WHERE `user_id` = ? AND `activity_type` IN ('course', 'lesson') ORDER BY `activity_type` ASC", [client.data.id], (error, rows) => {
		if(error) {
			console.log(error);
			return;
		}
		
		client.cprogs = {};
		
		rows.forEach(row => {
			if(!client.cprogs[row.courseId])
				client.cprogs[row.courseId] = [];
			
			client.cprogs[row.courseId].push(row);
		});
	});
}
function notifyNewPost(post) {
	mysql.query('INSERT INTO `postsnotified` (`postId`) VALUES (?)', [post.ID]);
	
	// If over 2 weeks old, don't notify
	if(time() - post.time > 86400 * 14)
		return;
	
	notifyAll('New video available', post.name, {type: 'video', videoPostSimple: {id: post.ID, name: post.name}}, 'allowNotifyContent');
	
	console.log(`Notified all about new video ${post.name}`);
}
function handleCommand(message, client) {
	if(message.charAt(0) != '/')
		return;
	
	const cmdMessage = message.substr(1, message.length);
	const args = cmdMessage.split(' ');
	const cmd = args.shift();
	
	switch(cmd.toUpperCase()) {
		case 'N': {
			const title = args.shift();
			const message = args.shift();
			const type = args.shift();
			
			let data;
			
			switch(type) {
				case 'video':
					data = {
						type: 'video',
						videoPostSimple: {id: 10427, name: 'Hamstrings and Hipz'}
					}
				break;
			}
			
			setTimeout(() => notify(client.data.id, title, message, data), 3000);
			
			break;
		}
	}
	
	console.log(`Handled command ${cmd} from ${client.data.id}`)
	
	return true;
}
function checkIsSubscriber(client) {
	mysql.query("SELECT u.ID AS `ID`, u.user_login AS `username`, u.user_email AS `email`, CONCAT(pm_last_name.meta_value, ', ', pm_first_name.meta_value) AS `name`, pm_first_name.meta_value AS `first_name`, pm_last_name.meta_value AS `last_name`, IFNULL(m.txn_count,0) AS `txn_count`, IFNULL(m.active_txn_count,0) AS `active_txn_count`, IFNULL(m.expired_txn_count,0) AS `expired_txn_count`, IFNULL(m.trial_txn_count,0) AS `trial_txn_count`, IFNULL(m.sub_count,0) AS `sub_count`, IFNULL(m.active_sub_count,0) AS `active_sub_count`, IFNULL(m.pending_sub_count,0) AS `pending_sub_count`, IFNULL(m.suspended_sub_count,0) AS `suspended_sub_count`, IFNULL(m.cancelled_sub_count,0) AS `cancelled_sub_count`, IFNULL(latest_txn.created_at,NULL) AS `latest_txn_date`, IFNULL(first_txn.created_at,NULL) AS `first_txn_date`, CASE WHEN active_txn_count>0 THEN 'active' WHEN trial_txn_count>0 THEN 'active' WHEN expired_txn_count>0 THEN 'expired' ELSE 'none' END AS `status`, IFNULL(m.memberships,'') AS `memberships`, IFNULL(m.inactive_memberships,'') AS `inactive_memberships`, IFNULL(last_login.created_at, NULL) AS `last_login_date`, IFNULL(m.login_count,0) AS `login_count`, IFNULL(m.total_spent,0.00) AS `total_spent`, u.user_registered AS `registered` FROM yogaform_wp670.wpaa_users AS u LEFT JOIN yogaform_wp670.wpaa_usermeta AS pm_first_name ON pm_first_name.user_id = u.ID AND pm_first_name.meta_key='first_name' LEFT JOIN yogaform_wp670.wpaa_usermeta AS pm_last_name ON pm_last_name.user_id = u.ID AND pm_last_name.meta_key='last_name' /* IMPORTANT */ JOIN yogaform_wp670.wpaa_mepr_members AS m ON m.user_id=u.ID LEFT JOIN yogaform_wp670.wpaa_mepr_transactions AS first_txn ON m.first_txn_id=first_txn.id LEFT JOIN yogaform_wp670.wpaa_mepr_transactions AS latest_txn ON m.latest_txn_id=latest_txn.id LEFT JOIN yogaform_wp670.wpaa_mepr_events AS last_login ON m.last_login_id=last_login.id WHERE (m.active_txn_count > 0 OR m.trial_txn_count > 0) AND (u.`ID` = ?) ORDER BY `registered` DESC", [client.data.id], (error, rows) => {
		client.isSubscriber = rows?.length > 0;
	});
}


function getDisplayName(account) {
	return account.nickname ?? account.user_login ?? (account.firstName.trim() + " " . account.lastName.trim());
}
function getSocialMedias(client) {
	return {
		strava: !!client.data.stravatoken
	}
}
function getMonday(d) {
	d = new Date(d);
	var day = d.getDay(),
	diff = d.getDate() - day + (day == 0 ? -6 : 1); // adjust when day is sunday
	return new Date(d.setDate(diff));
}




function notifyAll(title, body, data, field = 'allowNotifyBot') {
	mysql.query('SELECT DISTINCT `token` FROM `pushtokens` WHERE (SELECT `' + field + '` FROM `users` WHERE users.id = pushtokens.mpid) = 1', (error, rows) => {
		rows.forEach(row => sendExpoNotification(row.token, title, body, data));
	})
}
function notify(id, title, body, data) {
	mysql.query('SELECT DISTINCT `token` FROM `pushtokens` WHERE `mpid` = ?', [id], (error, rows) => {
		if(!rows)
			return;
		
		rows.forEach(row => sendExpoNotification(row.token, title, body, data));
	})
}
function sendVideoWatchHistory(client) {
	return new Promise((accept, reject) => {
		mysql.query(
			'SELECT * FROM videohistory WHERE `mpid` = ? ORDER BY `time` DESC', 
			[client.data.id], 
			(error, rows) => {
				if(error) {
					reject(error);
					return;
				}
				
				rows.forEach(row => {
					row.videoPost = wpPosts[row.post];
				});
				
				client.emit('watched', rows || []);
				client.watchHistory = rows;
				
				accept();
			}
		);
	});
}
function sendStats(client) {
	const monday = getMonday(new Date());
	
	const stats = {
		badges: (client.data.badges || '').split(','),
		videosWatchedWeek: 0,
		minsSpentWeek: 0,
		daysActiveWeek: 0,
		videosInADay: 0,
		daysStreak: 0
	};
	
	let lastDayDate;
	let lastDayDateWeek;
	
	const wh = [...client.watchHistory];
	
	wh.reverse();
	
	wh.forEach(row => {
		let d = new Date(row.time * 1000);
		
		if(row.delta > 0.8 && row.time >= monday.getTime() / 1000) {
			stats.videosWatchedWeek++;
			stats.minsSpentWeek += row.seconds / 60;
			
			if(lastDayDateWeek) {
				if(d.getDay() != lastDayDateWeek.getDay())
					stats.daysActiveWeek++;
			}else{
				stats.daysActiveWeek++;
			}
			
			lastDayDateWeek = d;
		}
		
		if(lastDayDate) {
			if(d.getDay() != lastDayDate.getDay())
				stats.daysStreak++;
		}else{
			stats.daysStreak++;
		}
		
		lastDayDate = new Date(row.time);
	});
	
	client.stats = stats;
	client.emit('stats', stats);
}
function checkAchieve(client) {
	if(client.isSubscriber && !client.stats.badges.includes('subscribed'))
		addBadge('subscribed', client);
		
	if(client.stats.videosWatchedWeek >= 1 && !client.stats.badges.includes('watchedvideo'))
		addBadge('watchedvideo', client);
	
	if(client.stats.videosWatchedWeek >= 7 && !client.stats.badges.includes('7videosweek'))
		addBadge('7videosweek', client);
	
	if(client.stats.videosInADay >= 2 && !client.stats.badges.includes('2videosday'))
		addBadge('2videosday', client);
	
	if(client.stats.daysActiveWeek >= 5 && !client.stats.badges.includes('active5'))
		addBadge('active5', client);
	
	if(client.stats.daysStreak >= 30 && !client.stats.badges.includes('30daystreak'))
		addBadge('30daystreak', client);
	
	if(client.stats.daysStreak >= 365 && !client.stats.badges.includes('1yearstreak'))
		addBadge('1yearstreak', client);
	
	if(!client.stats.badges.includes('finishedcourse')) {
		mysql.query("SELECT * FROM `coursesfinished` WHERE `mpid` = ?", [client.data.id], (error, rows) => {
			if(rows.length > 0)
				addBadge('finishedcourse', client);
		});
	}
}
function addBadge(id, client) {
	const badge = Badges[id];
	
	if(!badge)
		return;
	
	if(client.stats.badges.includes(id))
		return;
	
	client.stats.badges.push(id);
	client.data.badges = client.stats.badges.join(',');
	
	mysql.query('UPDATE users SET `badges` = ? WHERE `id` = ?', [client.data.badges, client.data.id]);
	
	if(client.data.allowNotifyRewards)
		notify(client.data.id, 'New badge earned!', `${badge.emoji} ${badge.label}`, {type: 'profile'});
	
	sendStats(client);
}

StartServer();