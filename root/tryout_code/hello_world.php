<?php

//### Query Settings

$site_settings = [
	'title'       => get_option('blogname'),
	'description' => get_option('blogdescription'),
	'url'         => get_option('siteurl'),
	'admin_email' => get_option('admin_email')
	/* etc. */
];

echo json_encode($site_settings, JSON_PRETTY_PRINT);
echo "\n\n";

//### Write Contents

$post_data = [
	'post_title'   => 'Dummy Post Title',
	'post_content' => "Dummy post content created from .php script",
	'post_status'  => 'publish',
	'post_author'  => get_current_user_id(),
	'post_type'    => 'post'
];

$post_id = wp_insert_post($post_data);

echo $post_id;
echo "\n";
