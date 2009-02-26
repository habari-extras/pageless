<?php
/**
 * Pageless Plugin
 */

require_once 'pagelesshandler.php';

class Pageless extends Plugin
{
	private $config = array();
	private $class_name = '';
	private static $handler_vars = array();

	private static function default_options()
	{
		return array (
			'num_item' => '3',
			'post_class' => 'hentry',
			'pager_id' => 'page-selector'
		);
	}

	/**
	 * Required plugin information
	 * @return array The array of information
	 **/
	public function info()
	{
		return array(
			'name' => 'Pageless',
			'version' => '0.2',
			'url' => 'http://code.google.com/p/bcse/wiki/Pageless',
			'author' => 'Joel Lee',
			'authorurl' => 'http://blog.bcse.info/',
			'license' => 'Apache License 2.0',
			'description' => _t('Give your blog the ability of infinite scrolling, instead of breaking content into ‘pages.’', $this->class_name)
		);
	}

	/**
	 * Add update beacon support
	 **/
	public function action_update_check()
	{
	 	Update::add('Pageless', '9b146781-0ee5-406a-aed5-90d661f76ade', $this->info->version);
	}

	/**
	 * Add actions to the plugin page for this plugin
	 * @param array $actions An array of actions that apply to this plugin
	 * @param string $plugin_id The string id of a plugin, generated by the system
	 * @return array The array of actions to attach to the specified $plugin_id
	 **/
	public function filter_plugin_config($actions, $plugin_id)
	{
		if ($plugin_id === $this->plugin_id()) {
			$actions[] = _t('Configure', $this->class_name);
		}

		return $actions;
	}

	/**
	 * Respond to the user selecting an action on the plugin page
	 * @param string $plugin_id The string id of the acted-upon plugin
	 * @param string $action The action string supplied via the filter_plugin_config hook
	 **/
	public function action_plugin_ui($plugin_id, $action)
	{
		if ($plugin_id === $this->plugin_id()) {
			switch ($action) {
				case _t('Configure', $this->class_name):
					$ui = new FormUI($this->class_name);

					$num_item = $ui->append('text', 'num_item', 'option:' . $this->class_name . '__num_item', _t('How many posts to load each time?', $this->class_name));
					$num_item->add_validator('validate_uint');
					$num_item->add_validator('validate_required');

					$post_class = $ui->append('text', 'post_class', 'option:' . $this->class_name . '__post_class', _t('CSS Class Name of Posts', $this->class_name));
					$post_class->add_validator('validate_required');

					$pager_id = $ui->append('text', 'pager_id', 'option:' . $this->class_name . '__pager_id', _t('ID of Page Selector', $this->class_name));
					$pager_id->add_validator('validate_required');

					// When the form is successfully completed, call $this->updated_config()
					$ui->append('submit', 'save', _t('Save', $this->class_name));
					$ui->set_option('success_message', _t('Options saved', $this->class_name));
					$ui->out();
					break;
			}
		}
	}

	public function validate_uint($value)
	{
		if (!ctype_digit($value) || strstr($value, '.') || $value < 0) {
			return array(_t('This field must be positive integer.', $this->class_name));
		}
		return array();
	}

	/**
	 * Returns true if plugin config form values defined in action_plugin_ui should be stored in options by Habari
	 * @return bool True if options should be stored
	 **/
	public function updated_config($ui)
	{
		return true;
	}

	/**
	 * On plugin activation, set the default options
	 */
	public function action_plugin_activation($file)
	{
		if (realpath($file) === __FILE__) {
			$this->class_name = strtolower(get_class($this));
			foreach (self::default_options() as $name => $value) {
				$current_value = Options::get($this->class_name . '__' . $name);
				if (is_null($current_value)) {
					Options::set($this->class_name . '__' . $name, $value);
				}
			}
		}
	}

	/**
	 * On plugin init, add the template included with this plugin to the available templates in the theme
	 */
	public function action_init()
	{
		$this->class_name = strtolower(get_class($this));
		foreach (self::default_options() as $name => $value) {
			$this->config[$name] = Options::get($this->class_name . '__' . $name);
		}
		$this->load_text_domain($this->class_name);
		$this->add_template('pageless', dirname(__FILE__) . '/pageless.php');
	}

	public function filter_rewrite_rules($rules)
	{
		$rules[] = new RewriteRule(array(
			'name' => 'display_pageless',
			'parse_regex' => '%^pageless/(?P<slug>[a-zA-Z0-9-]+)(?:/(?P<type>tag|date|search)/(?P<param>.+))?/?$%i',
			'build_str' => 'pageless/{$slug}(/{$type}/{$param})',
			'handler' => 'PagelessHandler',
			'action' => 'display_pageless',
			'rule_class' => RewriteRule::RULE_PLUGIN,
			'is_active' => 1,
			'description' => 'display_pageless'
		));
		$rules[] = new RewriteRule(array(
			'name' => 'display_pageless_js',
			'parse_regex' => '%^scripts/jquery.pageless_(?P<config>[0-9a-f]{32}).js$%i',
			'build_str' =>  'scripts/jquery.pageless_{$config}.js',
			'handler' => 'UserThemeHandler',
			'action' => 'display_pageless_js',
			'rule_class' => RewriteRule::RULE_PLUGIN,
			'is_active' => 1,
			'description' => 'display_pageless_js'
		));
		return $rules;
	}

	public function action_handler_display_pageless_js($handler_vars)
	{
		// If 'slug' exists, then it must be single, don't do anything
		if (!isset($handler_vars['slug'])) {
			// Determine act_display
			$filter_type = '';
			$filter_param = '';
			if (isset($handler_vars['tag'])) {
				$filter_type = 'tag';
				$filter_param = $handler_vars['tag'];
			} else
			if (isset($handler_vars['year'])) {
				$filter_type = 'date';
				$filter_param = $handler_vars['year'];
				if (isset($handler_vars['month'])) {
					$filter_param .= '/' . $handler_vars['month'];
				}
				if (isset($handler_vars['day'])) {
					$filter_param .= '/' . $handler_vars['day'];
				}
			} else
			if (isset($handler_vars['criteria'])) {
				$filter_type = 'search';
				$filter_param = $handler_vars['criteria'];
			}

			$out = '(function($){
	$(function() {
		$("#' . $this->config['pager_id'] . '").hide();

		$("#' . $this->config['pager_id'] . '").before("<div id=\"pageless-indicator\"></div>");
		var spinner = {
			start: function() {
				$("#pageless-indicator").spinner({height:32,width:32,speed:50,image:"' . $this->get_url(TRUE) . 'spinnersmalldark.png"});
				$("#pageless-indicator").show();
			},
			stop: function() {
				$("#pageless-indicator").spinner("stop");
				$("#pageless-indicator").hide();
			}
		}

		var the_end = false;

		function appendEntries() {
			if ($(window).scrollTop() >= $(document).height() - ($(window).height() * 2)) {
				var slug = $(".' . $this->config['post_class'] . ':last").attr("id").replace(/^(?:entry|page)-/, "");
				$.ajax({
					url: "' . URL::get('display_pageless', array('type' => $filter_type, 'param' => $filter_param)) . '".replace("{$slug}", slug),
					beforeSend: function() {
						spinner.start();
						$(window).unbind("scroll", appendEntries);
					},
					success: function(response) {
						if (response.length > 100) {
							$(".' . $this->config['post_class'] . ':last").after(response);
						} else {
							the_end = true;
						}
					},
					complete: function() {
						spinner.stop();
						if (!the_end && activated) {
							$(window).bind("scroll", appendEntries);
						}
					}
				});
			}
		}
		$(window).bind("scroll", appendEntries);

		var activated = true;

		function toggleScroll() {
			activated = !activated;
			if (!the_end && activated) {
				$(window).bind("scroll", appendEntries);
				$("#' . $this->config['pager_id'] . '").hide();
				appendEntries();
			} else {
				$(window).unbind("scroll", appendEntries);
				$("#' . $this->config['pager_id'] . '").show();
			}
		}
		$(document).bind("dblclick", toggleScroll);
	});
})(jQuery);';

			ob_clean();
			header('Content-type: text/javascript');
			header('ETag: ' . md5($out));
			header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 315360000) . ' GMT');
			header('Cache-Control: max-age=315360000');
			echo $out;
		}

		exit;
	}

	public function theme_footer()
	{
		if (count(self::$handler_vars) === 0)
			self::$handler_vars = Controller::get_handler_vars();

		// If 'slug' exists, then it must be single, don't do anything
		if (!isset(self::$handler_vars['slug'])) {
			// If jQuery is loaded in header, then do not load it again
			if (!Stack::has('template_header_javascript', 'jquery'))
				Stack::add('template_footer_javascript', 'http://ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js', 'jquery');
			Stack::add('template_footer_javascript', Site::get_url('scripts') . '/jquery.spinner.js', 'jquery.spinner', 'jquery');
			$params = new SuperGlobal($this->config);
			$params = $params->merge(self::$handler_vars);
			Stack::add('template_footer_javascript', URL::get('display_pageless_js', array('config' => md5(serialize($params)))), 'jquery.pageless', 'jquery');
		}
	}
}
?>
