<?php

namespace MediaWiki\Extension\Discourse;

use LogicException;
use MediaWiki\Config\ServiceOptions;

class ExtensionConfig {
	public const SERVICE_NAME = 'DiscourseConfig';
	public const LOG_CHANNEL = 'Discourse';
	public const API_KEY = 'DiscourseApiKey';
	public const API_USERNAME = 'DiscourseApiUsername';
	public const BASE_URL = 'DiscourseBaseUrl';
	public const BASE_URL_INTERNAL = 'DiscourseBaseUrlInternal';
	public const CONNECT_SECRET = 'DiscourseConnectSecret';
	public const DEFAULT_AVATAR_COLOR = 'DiscourseDefaultAvatarColor';
	public const ENABLE_CONNECT = 'DiscourseEnableConnect';
	public const ENABLE_NOTIFY = 'DiscourseEnableNotify';
	public const ENABLE_PROFILE = 'DiscourseEnableProfile';
	public const ENABLE_RELATED_ARTICLES = 'DiscourseEnableRelatedArticles';
	public const ENABLE_TALK_BUTTON = 'DiscourseEnableTalkButton';
	public const GROUP_MAP = 'DiscourseGroupMap';
	public const SUPPRESS_WELCOME_MESSAGE = 'DiscourseSuppressWelcomeMessage';
	public const UNIX_SOCKET = 'DiscourseUnixSocket';
	public const CONSTRUCTOR_OPTIONS = [
		self::API_KEY,
		self::API_USERNAME,
		self::BASE_URL,
		self::BASE_URL_INTERNAL,
		self::CONNECT_SECRET,
		self::DEFAULT_AVATAR_COLOR,
		self::ENABLE_CONNECT,
		self::ENABLE_NOTIFY,
		self::ENABLE_PROFILE,
		self::ENABLE_RELATED_ARTICLES,
		self::ENABLE_TALK_BUTTON,
		self::GROUP_MAP,
		self::SUPPRESS_WELCOME_MESSAGE,
		self::UNIX_SOCKET,
	];

	public function __construct(
		private readonly ServiceOptions $options,
	) {
		$this->options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	public function getApiKey(): string {
		$apiKey = $this->options->get( self::API_KEY );
		if ( $apiKey === false ) {
			throw new LogicException( '$wgDiscourseApiKey must be set' );
		}
		return $apiKey;
	}

	public function getApiUsername(): string {
		return $this->options->get( self::API_USERNAME );
	}

	public function getBaseUrl(): string {
		$baseUrl = $this->options->get( self::BASE_URL );
		if ( $baseUrl === false ) {
			throw new LogicException( '$wgDiscourseBaseUrl must be set' );
		}
		return $baseUrl;
	}

	public function getInternalBaseUrl(): string {
		return $this->options->get( self::BASE_URL_INTERNAL ) ?? $this->getBaseUrl();
	}

	public function getConnectSecret(): string|false {
		return $this->options->get( self::CONNECT_SECRET );
	}

	public function getDefaultAvatarColor(): string {
		return $this->options->get( self::DEFAULT_AVATAR_COLOR );
	}

	public function isConnectEnabled(): bool {
		return $this->options->get( self::ENABLE_CONNECT );
	}

	public function isNotifyEnabled(): bool {
		return $this->options->get( self::ENABLE_NOTIFY );
	}

	public function isProfileEnabled(): bool {
		return $this->options->get( self::ENABLE_PROFILE );
	}

	public function areRelatedArticlesEnabled(): bool {
		return $this->options->get( self::ENABLE_RELATED_ARTICLES );
	}

	public function isTalkButtonEnabled(): bool {
		return $this->options->get( self::ENABLE_TALK_BUTTON );
	}

	public function getGroupMap(): array|null {
		return $this->options->get( self::GROUP_MAP );
	}

	public function isWelcomeMessageSuppressed(): bool {
		return $this->options->get( self::SUPPRESS_WELCOME_MESSAGE );
	}

	public function getUnixSocket(): ?string {
		return $this->options->get( self::UNIX_SOCKET );
	}
}
