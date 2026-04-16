<?php

/**
 * EXAMPLE 01: TEXT TO MATH (EMBEDDINGS)
 *
 * This script demonstrates the fundamental starting point of modern AI: embeddings. 
 * Using `GPAI::wp_ai_client_embeds()`, we transform human language into high-dimensional numerical vectors. 
 *
 * Key Concepts:
 * - Vectorization: Converting text strings into arrays of numbers.
 * - Semantic Mapping: These numbers represent the "meaning" of the text in a mathematical space.
 * - Foundation: This is the prerequisite for all similarity-based searches and RAG workflows.
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

$inputs = [
	"The cat sits on the mat.",
	"A dog is playing in the park.",
	"Artificial intelligence is transforming technology."
];
$result = GPAI::wp_ai_client_embeds($inputs)->generate(true);
