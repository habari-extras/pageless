<?php
class PagelessHandler extends ActionHandler
{
	private $theme = null;
	private $default_fields = array(
		'slug' => ''
	);

	/**
	 * Constructor for the default theme handler.  Here, we
	 * automatically load the active theme for the installation,
	 * and create a new Theme instance.
	 */
	public function __construct()
	{
		$this->theme = Themes::create();
	}

	public function act_display_pageless()
	{
		$this->handler_vars = array_merge($this->default_fields, $this->handler_vars);
		$post = Post::get(array('slug' => $this->handler_vars['slug']));
		if ($post instanceof Post) {
			// Default params
			$params = array(
				'where' => "(pubdate < '{$post->pubdate->sql}' OR (pubdate = '{$post->pubdate->sql}' AND id < {$post->id})) AND content_type = {$post->content_type} AND status = {$post->status}",
				'limit' => Options::get('pageless__num_item'),
				'orderby' => 'pubdate DESC, id DESC'
				);

			// Additional filters, in other word, handling act_display
			if (isset($this->handler_vars['type'])) {
				if ($this->handler_vars['type'] === 'tag') {
					$params['tag_slug'] = $this->handler_vars['param'];
				} else
				if ($this->handler_vars['type'] === 'date') {
					$date = explode('/', $this->handler_vars['param']);
					$params_count = count($date);
					switch ($params_count) {
						case 3:
							$params['day'] = $date[2];
						case 2:
							$params['month'] = $date[1];
						case 1:
							$params['year'] = $date[0];
						default:
							break;
					}
				} else
				if ($this->handler_vars['type'] === 'search') {
					$params['criteria'] = $this->handler_vars['param'];
				}
			}

			// Get $posts -> Assign $posts to theme -> Display template
			$posts = Posts::get($params);
			$this->theme->assign('posts', $posts);
			$this->theme->display('pageless');
		}
	}
}
?>