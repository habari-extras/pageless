<?php foreach ($posts as $post) { ?>
		<div id="entry-<?php echo $post->slug; ?>" class="hentry entry <?php echo $post->statusname , ' ' , $post->tags_class; ?>">
			<div class="entry-head">
				<h2 class="entry-title"><a href="<?php echo $post->permalink; ?>" title="<?php echo strip_tags($post->title); ?>" rel="bookmark"><?php echo $post->title_out; ?></a></h2>
				<ul class="entry-meta">
					<li class="entry-date"><abbr class="published" title="<?php echo $post->pubdate_iso; ?>"><?php echo $post->pubdate_out; ?></abbr></li>
					<li class="entry-time"><abbr class="published" title="<?php echo $post->pubdate_iso; ?>"><?php echo $post->pubdate_time; ?></abbr></li>
					<li class="comments-link"><a href="<?php echo $post->permalink; ?>#comments" title="<?php _e('Comments to this post', $this->class_name) ?>"><?php printf(_n('%1$d Comment', '%1$d Comments', $post->comments->approved->count, $this->class_name), $post->comments->approved->count); ?></a></li>
<?php if (is_array($post->tags)) { ?>
					<li class="entry-tags"><?php echo $post->tags_out; ?></li>
<?php } ?>
<?php if (isset($user) && $user) { ?>
					<li class="entry-edit"><a href="<?php URL::out('admin', 'page=publish&slug=' . $post->slug); ?>" title="<?php _e('Edit post', $this->class_name) ?>"><?php _e('Edit', $this->class_name) ?></a></li>
<?php } ?>
				</ul>
			</div>
			<div class="entry-content">
				<?php echo $post->content_out; ?>
			</div>
		</div>
<?php } ?>
