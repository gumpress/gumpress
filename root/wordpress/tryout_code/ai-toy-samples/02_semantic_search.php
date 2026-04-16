<?php

/**
 * EXAMPLE 02: SEMANTIC SEARCH (NOT JUST KEYWORDS)
 *
 * Building on embeddings, this script introduces the `VectorStore` for indexing and retrieval. 
 * Instead of searching for exact characters (like a standard SQL LIKE query), we search for intent.
 *
 * Key Concepts:
 * - Vector Storage: Saving embeddings in a JSON-based database for persistence.
 * - Cosine Similarity: A mathematical measure used to find how "close" two ideas are in vector space.
 * - Intent Matching: Finding "reset password" instructions even if the user asks about "forgetting credentials".
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
	"How to reset your password in a web application.",
	"Steps to change your account password securely.",
	"Best practices for cooking pasta al dente.",
	"How to secure your online account with 2FA."
];

// Ingest
GPVS::ingest($inputs);

// Search
$query = "I forgot my password, what should I do?";
$query_embedding = GPAI::wp_ai_client_embeds($query)->generate()[0];
$query_resultset = GPVS::search($query_embedding, 3);

// Result
echo "Query: $query\n\n";
foreach ($query_resultset as $i => $qr) {
	if ($i > 0) echo "\n";
	echo "Score: " . round($qr['score'], 3) . "\n";
	echo "Input: " . $qr['text'] . "\n";
}
