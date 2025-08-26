# frozen_string_literal: true

module Jobs
  class NotifyMediawiki < ::Jobs::Base
    def execute(args)
      return unless SiteSetting.mediawiki_enabled? and SiteSetting.enable_discourse_connect
      args[:timestamp] = Time.now.utc
      Rails.logger.info("DiscourseNotify: sending notification #{args.to_json}")
      payload = Base64.strict_encode64(args.to_json)
      secret = SiteSetting.discourse_connect_secret
      signature = OpenSSL::HMAC.hexdigest('sha256', secret, payload)
      headers = {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      }
      headers['Host'] = SiteSetting.mediawiki_host_override unless SiteSetting.mediawiki_host_override.blank?
      result = Excon.post(SiteSetting.mediawiki_api_path,
        body: URI.encode_www_form(
          action: 'discoursenotify',
          format: 'json',
          payload: payload,
          signature: signature,
        ),
        headers: headers,
        ssl_verify_peer: SiteSetting.mediawiki_validate_tls)
      if result.status != 200
        Rails.logger.warn("DiscourseNotify: Failed with status #{result.status}, response: #{result.body}")
      end
    end
  end
end
