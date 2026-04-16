<?php

/**
 * GPVS: a toy vector store implementation for managing embeddings
 * Licensed under the MIT License.
 */

/**
 * This code is derived and inspired by the work of Paolo Mulas, licensed under the MIT License.
 * Source: https://github.com/paolomulas/datapizza-ai-php
 * A special thanks to the author for sharing his work.
 * Some of the original comments and explanations have been substantially preserved.
 */

class GPVS 
{
	private static $documents = [];     // In-memory document storage

	/**
	 * Add document
	 * 
	 * @param string $text Original document text
	 * @param array $embedding Numerical vector (e.g., 1536 floats)
	 * @param array $metadata Optional metadata
	 * @return string Unique document ID
	 */
	public static function add_document($text, $embedding, $metadata = [])
	{
		$doc_id = uniqid('doc_', true);
		self::$documents[$doc_id] = [
			'text'		=> $text,
			'embedding' => $embedding,
			'metadata'	=> $metadata,
			'created'	=> date('Y-m-d H:i:s')
		];
		return $doc_id;
	}    
	 
	/**
	 * Del document
	 * 
	 * @param string $doc_id Document ID to delete
	 * @return bool True if deleted, false if not found
	 */
	public static function del_document($doc_id)
	{
		if (isset(self::$documents[$doc_id])) {
			unset(self::$documents[$doc_id]);
			return true;
		}
		return false;
	}

	/**
	 * Returns document count
	 * 
	 * @return int Number of documents stored
	 */
	public static function length()
	{
		return count(self::$documents);
	}

	/**
	 * Searches for similar documents
	 * 
	 * Educational algorithm:
	 * 1. Loop through ALL documents (O(n))
	 * 2. Calculate cosine similarity with each
	 * 3. Sort by similarity (highest first)
	 * 4. Return top K results
	 * 
	 * Performance note:
	 * With 1000 documents and 1536-dim vectors:
	 * - 1000 similarity calculations
	 * - Each calculation: 1536 multiplications + additions
	 * - Total: ~1.5M operations
	 * - On Raspberry Pi: ~100ms
	 * 
	 * This is fine for learning but not for 1M+ documents!
	 * 
	 * @param array $query_embedding Query vector
	 * @param int $top_k Number of results to return
	 * @return array Top K most similar documents
	 */
	public static function search($query_embedding, $top_k = 5)
	{
		$results = [];
		// Calculate similarity with each document
		foreach (self::$documents as $doc_id => $doc) {
			$similarity = self::cosine_similarity($query_embedding, $doc['embedding']);
			$results[] = [
				'id'		  => $doc_id,
				'text'	  => $doc['text'],
				'score'	  => $similarity,
				'metadata' => $doc['metadata']
			];
		}
		// Sort by score descending (highest similarity first)
		usort($results, function($a, $b) {
			return $b['score'] <=> $a['score'];
		});
		// Return only top K results
		return array_slice($results, 0, $top_k);
	}

	/**
	 * Simplified ingestion for text strings (not files)
	 * 
	 * @param array $docs Array of text strings
	 * @param object $vectorstore VectorStore instance
	 * @param int $chunk_size Unused (kept for compatibility)
	 * @param int $chunk_overlap Unused (kept for compatibility)
	 * @param array $metadata Metadata to attach to all docs
	 * @return int Number of documents ingested
	 */
	public static function ingest($docs, $chunk_maxsize = null, $chunk_overlap = null, $metadata = array())
	{
		$count = 0;
		foreach ($docs as $doc) {
			$chunks = self::ingest_split($doc, $chunk_maxsize, $chunk_overlap);
			foreach ($chunks as $i => $chunk) {
				$chunk_embedding = GPAI::wp_ai_client_embeds($chunk)->generate()[0];
				$chunk_metadata  = array_merge($metadata, array(
					'chunk_index'	=> $i,
					'total_chunks'	=> count($chunks)
				));
				self::add_document($chunk, $chunk_embedding, $chunk_metadata);
				$label = (strlen($chunk) > 60) ? substr($chunk, 0, 60) . "..." : $chunk;
				echo "   ✓ Ingested: " . $label . " \n";
				$count++;
			}
		}
		echo "\n";
		return $count;
	}
	 
	/**
	 * Calculates cosine similarity between two vectors
	 * 
	 * Educational math lesson - Cosine similarity:
	 * 
	 * Formula: similarity = (A · B) / (||A|| × ||B||)
	 * 
	 * Where:
	 * - A · B = dot product (sum of element-wise multiplication)
	 * - ||A|| = magnitude of A (sqrt of sum of squares)
	 * - ||B|| = magnitude of B
	 * 
	 * Example with 2D vectors:
	 * A = [3, 4], B = [6, 8]
	 * A · B = 3*6 + 4*8 = 18 + 32 = 50
	 * ||A|| = sqrt(3² + 4²) = sqrt(25) = 5
	 * ||B|| = sqrt(6² + 8²) = sqrt(100) = 10
	 * similarity = 50 / (5 * 10) = 50/50 = 1.0 (identical direction!)
	 * 
	 * Why cosine similarity?
	 * - Measures angle, not distance
	 * - Works for high-dimensional vectors (1536 dimensions!)
	 * - Range -1 to 1 (easy to interpret)
	 * - Immune to vector magnitude (scale-invariant)
	 * 
	 * @param array $vec1 First vector (array of floats)
	 * @param array $vec2 Second vector (same length as vec1)
	 * @return float Similarity score between -1 and 1
	 */
	private static function cosine_similarity($vec1, $vec2)
	{
		$dot_product = 0.0;	// A · B
		$norm1		 = 0.0;	// ||A||²
		$norm2		 = 0.0;	// ||B||²
		  
		// Calculate dot product and norms in single loop
		// This is O(d) where d = vector dimensions
		for ($i = 0; $i < count($vec1); $i++) {
			$dot_product += $vec1[$i] * $vec2[$i];
			$norm1		 += $vec1[$i] * $vec1[$i];
			$norm2		 += $vec2[$i] * $vec2[$i];
		}
		  
		// Calculate magnitudes (square root of sum of squares)
		$norm1 = sqrt($norm1);
		$norm2 = sqrt($norm2);
		  
		// Handle zero vectors (no direction = undefined similarity)
		if ($norm1 == 0 || $norm2 == 0) {
			return 0.0;
		}
		  
		// Final cosine similarity
		return $dot_product / ($norm1 * $norm2);
	}

	private static function ingest_split($text, $chunk_maxsize = null, $chunk_overlap = null)
	{
		$chunk_maxsize	= $chunk_size	  ?? 500;	// 1000
		$chunk_overlap	= $chunk_overlap ??  50;	//  200

		// Handle edge case: text shorter than chunk size
		if (strlen($text) <= $chunk_maxsize) {
			return array($text);
		}
    
		$chunks = array();
		$start = 0;
		$text_length = strlen($text);
    
		while ($start < $text_length) {

			// Calculate end position for this chunk
			$end = min($start + $chunk_maxsize, $text_length);
        
			// Find smart boundary (sentence or paragraph end)
			// This prevents splitting mid-sentence
			if ($end < $text_length) {
				$boundary = self::ingest_split_find_boundary($text, $start, $end);
				if ($boundary !== false) {
					$end = $boundary;
				}
			}
        
			// Extract chunk
			$chunk = substr($text, $start, $end - $start);
			$chunks[] = trim($chunk);
        
			// Move to next chunk with overlap
			// Overlap ensures context continuity between chunks
			$start = $end - $chunk_overlap;
        
			// Ensure we make progress (avoid infinite loop)
			if ($start <= 0) {
				$start = $end;
			}
		}

		return $chunks;
	}

	/**
	 * Finds "smart" boundary for chunk splitting
	 * 
	 * Educational concept - Smart boundaries:
	 * Don't split mid-word or mid-sentence!
	 * Look for natural break points:
	 * 1. Paragraph breaks (\n\n)
	 * 2. Sentence ends (. ! ?)
	 * 3. Comma/semicolon (if nothing else)
	 * 
	 * This preserves semantic meaning in each chunk.
	 * 
	 * @param string $text Full text
	 * @param int $start Start position
	 * @param int $end Proposed end position
	 * @return int|false Position of smart boundary, or false if not found
	 */
	private static function ingest_split_find_boundary($text, $start, $end)
	{
		// Look backwards from end for good split point
		$search_range = min(200, $end - $start);
		$search_start = max($start, $end - $search_range);
		$search_text = substr($text, $search_start, $end - $search_start);
    
		// Priority 1: Paragraph break (double newline)
		$pos = strrpos($search_text, "\n\n");
		if ($pos !== false) {
			return $search_start + $pos + 2;
		}
    
		// Priority 2: Sentence end (. ! ?)
		$sentence_endings = array('. ', '! ', '? ', ".\n", "!\n", "?\n");
		$best_pos = false;
    
		foreach ($sentence_endings as $ending) {
			$pos = strrpos($search_text, $ending);
			if ($pos !== false && ($best_pos === false || $pos > $best_pos)) {
				$best_pos = $pos + strlen($ending);
			}
		}
    
		if ($best_pos !== false) {
			return $search_start + $best_pos;
		}
    
		// Priority 3: Comma or semicolon
		$pos = strrpos($search_text, ', ');
		if ($pos !== false) {
			return $search_start + $pos + 2;
		}
    
		$pos = strrpos($search_text, '; ');
		if ($pos !== false) {
			return $search_start + $pos + 2;
		}
    
		// Priority 4: Any whitespace
		$pos = strrpos($search_text, ' ');
		if ($pos !== false) {
			return $search_start + $pos + 1;
		}
    
		// No good boundary found - split at hard limit
		return false;
	}
}
