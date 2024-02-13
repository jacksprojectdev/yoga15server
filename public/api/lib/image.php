<?php
	function downloadResizeImage($url, $width, $height) {
		// Initialize cURL session
		$ch = curl_init();
		
		// Set cURL options
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($ch, CURLOPT_TIMEOUT, 60);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		
		// Set a User-Agent header to make the request appear as coming from a browser
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3');
		
		// Execute the cURL session
		$imageData = curl_exec($ch);
		
		// Check if cURL request was successful
		if (curl_errno($ch) !== 0) {
			curl_close($ch);
			return false;
		}
		
		// Get HTTP status code
		$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		
		// Close the cURL session
		curl_close($ch);
		
		// Check if the cURL request returned a successful status code
		if ($httpStatus !== 200) {
			return false;
		}
	
		// Create an image resource from the downloaded data
		$originalImage = imagecreatefromstring($imageData);
	
		// Check if the image resource was created successfully
		if ($originalImage === false) {
			return false;
		}
	
		// Get the original image dimensions
		$originalWidth = imagesx($originalImage);
		$originalHeight = imagesy($originalImage);
	
		// Calculate the aspect ratios to maintain the image proportions
		$aspectRatio = $originalWidth / $originalHeight;
		$newAspectRatio = $width / $height;
	
		// Calculate the new dimensions to fit within the specified width and height
		if ($newAspectRatio > $aspectRatio) {
			$newWidth = $height * $aspectRatio;
			$newHeight = $height;
		} else {
			$newWidth = $width;
			$newHeight = $width / $aspectRatio;
		}
	
		// Create a new blank image with the specified width and height
		$newImage = imagecreatetruecolor($width, $height);
	
		// Resize and copy the original image to the new image resource
		imagecopyresampled(
			$newImage,
			$originalImage,
			0,
			0,
			0,
			0,
			$newWidth,
			$newHeight,
			$originalWidth,
			$originalHeight
		);
	
		// Output the new image as a base64 data URL
		ob_start();
		imagepng($newImage); // You can use other image formats like imagejpeg if needed
		$imageData = ob_get_clean();
		imagedestroy($originalImage);
		imagedestroy($newImage);
	
		return 'data:image/png;base64,' . base64_encode($imageData);
	}
	function downloadResizeImageAndSave($url, $width, $height) {
		// Initialize cURL session
		$ch = curl_init();
		
		// Set cURL options
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($ch, CURLOPT_TIMEOUT, 60);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		
		// Cookies
		curl_setopt($ch, CURLOPT_COOKIEJAR, '/var/www/yoga15/resources/cookies.txt');
		curl_setopt($ch, CURLOPT_COOKIEFILE, '/var/www/yoga15/resources/cookies.txt');
		
		// Set a User-Agent header to make the request appear as coming from a browser
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3');

		// Execute the cURL session
		$imageData = curl_exec($ch);
		
		// Check if cURL request was successful
		if (curl_errno($ch) !== 0) {
			curl_close($ch);
			return false;
		}
		
		// Get HTTP status code
		$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		
		// Close the cURL session
		curl_close($ch);
		
		// Check if the cURL request returned a successful status code
		if ($httpStatus !== 200) {
			return false;
		}
	
		// Create an image resource from the downloaded data
		$originalImage = imagecreatefromstring($imageData);
		
		// Check if the image resource was created successfully
		if ($originalImage === false) {
			return false;
		}
		
		// Get the original image dimensions
		$originalWidth = imagesx($originalImage);
		$originalHeight = imagesy($originalImage);
		
		// Calculate the aspect ratios of the original image and the target canvas
		$aspectRatioImage = $originalWidth / $originalHeight;
		$aspectRatioCanvas = $width / $height;
		
		// Determine the dimensions to fit the entire square box (cover behavior)
		if ($aspectRatioImage > $aspectRatioCanvas) {
			// Image is wider than the canvas, crop the width and center vertically
			$newWidth = $originalHeight * $aspectRatioCanvas;
			$newHeight = $originalHeight;
			$x = ($originalWidth - $newWidth) / 2;
			$y = 0;
		} else {
			// Image is taller than the canvas, crop the height and center horizontally
			$newHeight = $originalWidth / $aspectRatioCanvas;
			$newWidth = $originalWidth;
			$x = 0;
			$y = ($originalHeight - $newHeight) / 2;
		}
		
		// Create a new blank image with a transparent background and the specified width and height
		$newImage = imagecreatetruecolor($width, $height);
		imagesavealpha($newImage, true);
		$transparentColor = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
		imagefill($newImage, 0, 0, $transparentColor);
		
		// Resize and copy the original image to the new image resource with cover behavior
		imagecopyresampled(
			$newImage,
			$originalImage,
			0,
			0,
			$x,
			$y,
			$width,
			$height,
			$newWidth,
			$newHeight
		);
		
		// Generate a unique file name for the saved image based on the URL
		$filename = '/var/www/yoga15/resources/' . sha1('y15v3' . $url) . '.png';
		
		// Save the new image as a PNG file
		$saveResult = imagepng($newImage, $filename);
		
		// Clean up resources
		imagedestroy($originalImage);
		imagedestroy($newImage);
		
		// Check if the image was saved successfully
		if (!$saveResult) {
			return false;
		}
		
		return $filename; // Return the path to the saved image
	}
?>