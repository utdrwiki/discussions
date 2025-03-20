# frozen_string_literal: true

# name: mediawiki
# label: MediaWiki
# about: Integrates MediaWiki with Discourse more closely.
# version: 0.0.1
# authors: Luka Simić
# url: https://github.com/utdrwiki/discussions
# required_version: 2.7.0

enabled_site_setting :mediawiki_enabled

require "excon"
require "openssl"

module ::MediaWiki
  PLUGIN_NAME = "mediawiki"
end

require_relative "lib/mediawiki"

after_initialize do
  require_relative "lib/notify_mediawiki"

  on(:notification_created) do |notification|
    return if notification.user.single_sign_on_record.nil?
    user_id = notification.user.single_sign_on_record.external_id
    data = JSON.parse(notification.data)
    Jobs.enqueue(:notify_mediawiki,
      event_type: 'notification',
      user_id: user_id,
      topic_id: notification.topic&.id,
      actor_username: data['original_username'],
      topic_title: notification.topic&.title,
      post_number: notification.post&.post_number,
      notification_type: notification.notification_type)
  end

  on(:user_updated) do |user|
    return if user.single_sign_on_record.nil?
    user_id = user.single_sign_on_record.external_id
    Jobs.enqueue(:notify_mediawiki,
      event_type: 'user_updated',
      user_id: user_id)
  end
end
