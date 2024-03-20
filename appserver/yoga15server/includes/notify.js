const axios = require('axios');

const expoNotificationServerKey = '13yck50rvcB-wvTJaI86wZSzFWNIwpbv9UXh2V6D';

async function sendExpoNotification(deviceToken, title, body, data) {
  // Prepare the notification payload
  const notification = {
	to: deviceToken,
	title,
	body,
	data,
	sound: 'default',
  };

  // Create headers with the Expo push notification server key
  const headers = {
	'Content-Type': 'application/json',
	Accept: 'application/json',
	Authorization: `Bearer ${expoNotificationServerKey}`,
  };
  
  const response = await axios.post('https://exp.host/--/api/v2/push/send', notification, {
	headers,
  });
  
  console.log(response.data, deviceToken);
}

module.exports = sendExpoNotification;