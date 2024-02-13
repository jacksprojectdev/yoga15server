<?php
	class WordPressPasswordVerifier {
		
		/**
		 * Verifies a password against a WordPress-generated hash.
		 *
		 * @param string $password The password to verify.
		 * @param string $hash     The WordPress-generated hash to compare against.
		 *
		 * @return bool True if the password is valid, false otherwise.
		 */
		public static function verify($password, $hash) {
			$result = password_verify($password, $hash);
			
			// Handle legacy WordPress hashes (prior to version 2.5).
			if (!$result && self::isLegacyHash($hash)) {
				require_once 'class-phpass.php'; // Include the compatibility library.
				$wp_hasher = new PasswordHash(8, true); // Create an instance of the compatibility library.
				$result = $wp_hasher->CheckPassword($password, $hash);
			}
			
			return $result;
		}
		
		/**
		 * Generates a legacy WordPress password hash from a string.
		 *
		 * @param string $password The password to generate the hash from.
		 *
		 * @return string The generated legacy WordPress password hash.
		 */
		public static function generateLegacyHash($password) {
			require_once 'class-phpass.php'; // Include the compatibility library.
			$wp_hasher = new PasswordHash(8, true); // Create an instance of the compatibility library.
			return $wp_hasher->HashPassword($password);
		}
		
		/**
		 * Checks if a hash is a legacy WordPress hash.
		 *
		 * @param string $hash The hash to check.
		 *
		 * @return bool True if the hash is a legacy WordPress hash, false otherwise.
		 */
		private static function isLegacyHash($hash) {
			return preg_match('/^\$P\$.{31}$/', $hash) === 1;
		}
	}
?>