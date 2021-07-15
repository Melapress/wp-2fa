<?php


namespace WP2FA;

/**
 * Plain old PHP object to hold data for an email template.
 *
 * @package WP2FA
 */
class EmailTemplate {

	/**
	 * @var string Template ID used for most settings form fields and setting keys.
	 */
	private $id;
	private $title;
	private $description;

	/**
	 * @var string ID used for identifying the subject and body of the email. Defaults to $id.
	 */
	private $email_content_id;

	/**
	 * @var bool True if the email can be turned on or off in the plugin settings.
	 */
	private $can_be_toggled = true;

	/**
	 * EmailTemplate constructor.
	 *
	 * @param string $id
	 * @param string $title
	 * @param string $description
	 */
	public function __construct( string $id, string $title, string $description ) {
		$this->id               = $id;
		$this->title            = $title;
		$this->description      = $description;
		$this->email_content_id = $id;

		return $this;
	}

	/**
	 * @return bool
	 */
	public function canBeToggled(): bool {
		return $this->can_be_toggled;
	}

	/**
	 * @param bool $can_be_toggled
	 */
	public function setCanBeToggled( $can_be_toggled ) {
		$this->can_be_toggled = $can_be_toggled;
	}

	/**
	 * @return string
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * @return string
	 */
	public function getTitle(): string {
		return $this->title;
	}

	/**
	 * @return string
	 */
	public function getDescription(): string {
		return $this->description;
	}

	/**
	 * @return string
	 */
	public function getEmailContentId(): string {
		return $this->email_content_id;
	}

	/**
	 * @param string $email_content_id
	 */
	public function setEmailContentId( string $email_content_id ) {
		$this->email_content_id = $email_content_id;
	}
}
