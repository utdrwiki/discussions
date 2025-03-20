# frozen_string_literal: true

module ::MediaWiki
  class Engine < ::Rails::Engine
    engine_name PLUGIN_NAME
    isolate_namespace MediaWiki
  end
end
