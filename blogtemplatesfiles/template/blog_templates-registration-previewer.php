<div id="blog_template-selection">
	<h3><?php _e('Select a template', 'blog_templates') ?></h3>
	<div class="blog_template-option">
		
	<?php 
	foreach ($templates as $tkey => $template) { 
		nbt_render_theme_selection_item( 'previewer', $tkey, $template, $this->options );
	}
	?>
	<div style="clear:both;"></div>
	</div>
</div>
