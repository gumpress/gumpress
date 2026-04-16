<?php

/**
 * EXAMPLE 03: PRODUCTION AUTOMATION (RAG TO WORDPRESS)
 *
 * The final evolution. This script orchestrates a complete workflow, transforming 
 * raw data into structured, published content within a real-world CMS.
 *
 * Key Concepts:
 * - Batch Processing: Ingesting a themed dataset and organizing it into a searchable library.
 * - Context Deduplication: Cleaning up retrieved data to ensure the model gets the most efficient prompt.
 * - Application Integration: Using `wp_insert_post` to turn AI-generated completions into actual WordPress drafts.
 * - Parameter Tuning: Controlling the model's creativity using temperature and frequency_penalty settings.
 */

/**
 * This code is derived and inspired by the work of Paolo Mulas, licensed under the MIT License.
 * Source: https://github.com/paolomulas/datapizza-ai-php
 * A special thanks to the author for sharing his work.
 * Some of the original comments and explanations have been substantially preserved.
 *
 * Licensed under the MIT License.
 */

require_once __DIR__ . '/base/class-gpai.php';
require_once __DIR__ . '/base/class-gpvs.php';

// inputs (short + clear for small model)
$inputs = [
	// About Local AI
	"Local AI models can run directly on your machine without internet access.",
	"Running AI locally improves privacy because data stays on your server.",
	"Small AI models are fast and efficient for simple tasks.",
	// About Security
	"Using strong passwords helps protect your online accounts.",
	"Two-factor authentication adds an extra layer of security.",
	"Keeping software updated reduces security risks.",
	// About Productivity
	"Automation can save time on repetitive tasks.",
	"Simple tools can improve daily productivity.",
	"Organizing tasks helps people work more efficiently."
];

GPVS::ingest($inputs);

$queries = [
	"Write about local AI benefits",
	"Write about online security basics"
];

foreach ($queries as $i => $query) {
	$query_embedding = GPAI::wp_ai_client_embeds($query)->generate()[0];
	$query_resultset = GPVS::search($query_embedding, 2);
	$context			  = build_context($query_resultset);
	$content			  = generate_post_from_context($query, $context);

	create_wp_post_from_text($content);
	if ($i > 0) echo "\n";
	echo "\e[36;1m[ GENERATED POST $i ]\e[30;1m\n\n";
	echo "Context:\n\e[37;1m$context\e[30;1m\n";
	echo "Content:\n\e[32m$content\e[30;1m\n";
}

function build_context($results)
{
	$context = "";
	$seen		= [];
	foreach ($results as $r) {
		$text = trim($r['text']);
		// Simple deduplication (based on first chars)
		$hash = md5(substr($text, 0, 60));
		if (isset($seen[$hash])) continue;
		$seen[$hash] = true;
		$context .= "- " . $text . "\n";
	}
	return $context;
}

function generate_post_from_context($topic, $context)
{
	$prompt =
		"Context:\n$context\n\n" .
		"Task:\nMerge all the sentences listed in Context into one short fluent paragraph.\n\n" 		
	;
	$result = GPAI::wp_ai_client_prompt( $prompt )
		->using_temperature( 0.0 )
		->using_frequency_penalty( 0.5 )
		->generate_text();

	return $result;
}

function create_wp_post_from_text($text)
{
	$lines	= explode("\n", trim($text));
	$title	= trim(array_shift($lines));
	$content = implode("\n", $lines);

	return wp_insert_post([
		'post_title'	=> sanitize_text_field($title),
		'post_content'	=> wp_kses_post($content),
		'post_status'	=> 'draft',
		'post_author'	=> get_current_user_id(),
		'tags_input'	=> ['AI', 'Generated']
	]);
}
