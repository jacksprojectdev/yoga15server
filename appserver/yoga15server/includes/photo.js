const fs = require('fs');
const sharp = require('sharp');

function savePhoto(base64ImageData, uniqueId) {
  return new Promise((accept, reject) => {
	// Extracting the mime type and base64 data from the input
	base64ImageData = String(base64ImageData);

	const matches = base64ImageData.match(/^data:(.+);base64,(.+)$/);

	if (!matches || matches.length !== 3) {
	  reject('Invalid base64 image data');
	}

	const [, mimeType, base64Data] = matches;

	// Validating the MIME type against a list of allowed types
	const allowedMimeTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif'];
	if (!allowedMimeTypes.includes(mimeType)) {
	  reject('Invalid MIME type');
	}

	// Creating a Buffer from base64 data
	const imageBuffer = Buffer.from(base64Data, 'base64');

	// Resize the image to be at most 128 pixels wide while maintaining aspect ratio
	sharp(imageBuffer)
	  .resize({ width: 128 })
	  .toBuffer()
	  .then((resizedBuffer) => {
		// Generating a unique filename
		const fileName = `pfp_${uniqueId}.${mimeType.split('/')[1]}`;

		// Path to the directory where you want to save the resized image
		const directoryPath = '/var/www/yoga15/resources'; // Replace with your desired directory path

		// Creating the directory if it doesn't exist
		if (!fs.existsSync(directoryPath)) {
		  fs.mkdirSync(directoryPath);
		}

		// Path to the resized image file
		const filePath = `${directoryPath}/${fileName}`;

		// Writing the resized image buffer to the file
		fs.writeFile(filePath, resizedBuffer, (err) => {
		  if (err) {
			reject(err);
		  } else {
			accept(fileName);
		  }
		});
	  })
	  .catch((err) => {
		reject(`Error resizing the image: ${err}`);
	  });
  });
}

module.exports.savePhoto = savePhoto;
