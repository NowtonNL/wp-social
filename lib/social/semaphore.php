<?php
/**
 * Semaphore Lock Management
 *
 * @package Social
 */
final class Social_Semaphore {

	/**
	 * Initializes the semaphore object.
	 *
	 * @static
	 * @return Social_Semaphore
	 */
	public static function factory() {
		return new self;
	}

	/**
	 * Attempts to start the lock. If the rename works, the lock is started.
	 *
	 * @return bool
	 */
	public function lock() {
		global $wpdb;

		// Attempt to set the lock
		$affected = $wpdb->query("
			UPDATE $wpdb->options
			   SET option_name = 'social_locked'
			 WHERE option_name = 'social_unlocked'
		");

		if ($affected == '0' and !$this->stuck_check()) {
			return false;
		}

		// Check to see if all processes are complete
		$affected = $wpdb->query("
			UPDATE $wpdb->options
			   SET option_value = CAST(option_value AS UNSIGNED) + 1
			 WHERE option_name = 'social_semaphore'
			   AND option_value = '0'
		");
		if ($affected != '1') {
			if (!$this->stuck_check()) {
				return false;
			}

			// Reset the semaphore to 1
			$wpdb->query("
				UPDATE $wpdb->options
				   SET option_value = '1'
				 WHERE option_name = 'social_semaphore'
			");
		}

		// Set the lock time
		$wpdb->query($wpdb->prepare("
			UPDATE $wpdb->options
			   SET option_value = %s
			 WHERE option_name = 'social_last_lock_time'
		", current_time('mysql', 1)));

		return true;
	}

	/**
	 * Increment the semaphore.
	 *
	 * @return Social_Semaphore
	 */
	public function increment() {
		global $wpdb;

		$wpdb->query("
			UPDATE $wpdb->options
			   SET option_value = CAST(option_value AS UNSIGNED) + 1
			 WHERE option_name = 'social_semaphore'
		");

		return $this;
	}

	/**
	 * Decrements the semaphore.
	 *
	 * @return Social_Semaphore
	 */
	public function decrement() {
		global $wpdb;

		$wpdb->query("
			UPDATE $wpdb->options
			   SET option_value = CAST(option_value AS UNSIGNED) - 1
			 WHERE option_name = 'social_semaphore'
			   AND CAST(option_value AS UNSIGNED) > 0
		");

		return $this;
	}

	/**
	 * Unlocks the process.
	 *
	 * @return bool
	 */
	public function unlock() {
		global $wpdb;

		$result = $wpdb->query("
			UPDATE $wpdb->options
			   SET option_name = 'social_unlocked'
			 WHERE option_name = 'social_locked'
		");

		if ($result == '1') {
			return true;
		}

		return false;
	}

	/**
	 * Attempts to jiggle the stuck lock loose.
	 *
	 * @return bool
	 */
	private function stuck_check() {
		global $wpdb;

		$affected = $wpdb->query($wpdb->prepare("
			UPDATE $wpdb->options
			   SET option_value = %s
			 WHERE option_name = 'social_last_lock_time'
			   AND option_value <= DATE_SUB(%s, INTERVAL 1 HOUR)
		", current_time('mysql', 1), current_time('mysql', 1)));

		if ($affected == '1') {
			return true;
		}

		return false;
	}

} // End Social_Semaphore
