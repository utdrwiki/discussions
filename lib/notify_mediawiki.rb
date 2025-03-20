# frozen_string_literal: true

module Jobs
  class NotifyMediawiki < ::Jobs::Base
    def execute(args)
      return unless SiteSetting.mediawiki_enabled? and SiteSetting.enable_discourse_connect
      args[:timestamp] = Time.now.utc
      payload = Base64.strict_encode64(args.to_json)
      secret = SiteSetting.discourse_connect_secret
      signature = OpenSSL::HMAC.hexdigest('sha256', secret, payload)
      headers = {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      }
      headers['Host'] = SiteSetting.mediawiki_host_override unless SiteSetting.mediawiki_host_override.blank?
      Excon.post(SiteSetting.mediawiki_api_path,
        body: URI.encode_www_form(
          action: 'discoursenotify',
          format: 'json',
          payload: payload,
          signature: signature,
        ),
        headers: headers,
        ssl_verify_peer: SiteSetting.mediawiki_validate_tls)
    end
  end
end
