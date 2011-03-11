<?php
class PagelessHandler extends ActionHandler
{
	public $theme = null;
	private $default_fields = array(
		'slug' => ''
	);

	/**
	 * Constructor for the default theme handler.  Here, we
	 * automatically load the active theme for the installation,
	 * and create a new Theme instance.
	 */
	public function __construct( )
	{
		$this->theme = Themes::create( );
	}

	private static function options( )
	{
		$default_options = array(
			'post_class' => 'hentry',
			'pager_id' => 'page-selector'
		);
		return Plugins::filter( 'pageless_options', $default_options );
	}

	public function act_display_pageless( )
	{
		// Get current post filters (Filters are different in tag archives, home, search results, etc.)
		$filters = new SuperGlobal( $this->default_fields );
		$filters = $filters->merge( $this->handler_vars );
		// Get the last post (We'll get posts older than this one later.)
		$post = Post::get( array( 'slug' => $filters[ 'slug' ] ) );
		if ( $post instanceof Post ) {
			// Default params
			$params = array(
				'where' => "( pubdate < '{$post->pubdate->sql}' OR ( pubdate = '{$post->pubdate->sql}' AND id < {$post->id} ) ) AND content_type = {$post->content_type} AND status = {$post->status}",
				'limit' => Options::get( 'pageless__num_item' ),
				'orderby' => 'pubdate DESC, id DESC'
				);

			// Additional filters, in other word, handling act_display
			if ( isset( $filters[ 'type' ] ) ) {
				if ( $filters[ 'type' ] === 'tag' ) {
					$params[ 'tag_slug' ] = $filters[ 'param' ];
				} else
				if ( $filters[ 'type' ] === 'date' ) {
					$date = explode( '/', $filters[ 'param' ] );
					$params_count = count( $date );
					switch ( $params_count ) {
						case 3:
							$params[ 'day' ] = $date[ 2 ];
						case 2:
							$params[ 'month' ] = $date[ 1 ];
						case 1:
							$params[ 'year' ] = $date[ 0 ];
						default:
							break;
					}
				} else
				if ( $filters[ 'type' ] === 'search' ) {
					$params[ 'criteria' ] = $filters[ 'param' ];
				}
			}

			// Get $posts -> Assign $posts to theme -> Display template
			$posts = Posts::get( $params );
			$this->theme->assign( 'posts', $posts );
			$this->theme->display( 'pageless' );
		}
	}

	public function act_display_pageless_js( $handler_vars )
	{
		// If 'slug' exists, then it must be single, don't do anything
		if ( !isset( $handler_vars[ 'slug' ] ) ) {
			// Determine act_display
			$filter_type = '';
			$filter_param = '';
			if ( isset( $handler_vars[ 'tag' ] ) ) {
				$filter_type = 'tag';
				$filter_param = $handler_vars[ 'tag' ];
			} else
			if ( isset( $handler_vars[ 'year' ] ) ) {
				$filter_type = 'date';
				$filter_param = $handler_vars[ 'year' ];
				if ( isset( $handler_vars[ 'month' ] ) ) {
					$filter_param .= '/' . $handler_vars[ 'month' ];
				}
				if ( isset( $handler_vars[ 'day' ] ) ) {
					$filter_param .= '/' . $handler_vars[ 'day' ];
				}
			} else
			if ( isset( $handler_vars[ 'criteria' ] ) ) {
				$filter_type = 'search';
				$filter_param = $handler_vars[ 'criteria' ];
			}

			$active_types = array_flip( Post::list_active_post_types( ) );
			unset( $active_types[ 0 ] );
			$active_types = implode( '|', $active_types );

			// Get settings
			$options = self::options( );

			$out = '(function($){
	$(function() {
		$("#' . $options[ 'pager_id' ] . '").hide();

		$("#' . $options[ 'pager_id' ] . '").before("<div id=\"pageless-indicator\"></div>");
		var spinner = {
			start: function() {
				$("#pageless-indicator").html(\'<img src="' . Site::get_url( 'admin_theme' ) . '/images/spin.gif">\');
				$("#pageless-indicator").show();
			},
			stop: function() {
				$("#pageless-indicator").hide();
			}
		}

		var the_end = false;

		function appendEntries() {
			if ($(window).scrollTop() >= $(document).height() - ($(window).height() * 2)) {
				var slug = $(".' . $options[ 'post_class' ] . ':last").attr("id").replace(/^(?:' . $active_types . ')-/, "");
				$.ajax({
					url: "' . URL::get( 'display_pageless', array( 'type' => $filter_type, 'param' => $filter_param ) ) . '".replace("{$slug}", slug),
					beforeSend: function() {
						spinner.start();
						$(window).unbind("scroll", appendEntries);
					},
					success: function(response) {
						if (response.length > 100) {
							$(".' . $options[ 'post_class' ] . ':last").after(response);
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
				$("#' . $options[ 'pager_id' ] . '").hide();
				appendEntries();
			} else {
				$(window).unbind("scroll", appendEntries);
				$("#' . $options[ 'pager_id' ] . '").show();
			}
		}
		$(document).bind("dblclick", toggleScroll);
	});
})(jQuery);';

			ob_clean( );
			header( 'Content-type: text/javascript' );
			header( 'ETag: ' . md5( $out ) );
			header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time( ) + 315360000 ) . ' GMT' );
			header( 'Cache-Control: max-age=315360000' );
			echo $out;
		}

		exit;
	}
}
?>