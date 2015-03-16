<?php

return array(
    'settings' => array(
        'index' => array(
            'number_of_shards' => (int) apply_filters( 'ep_default_index_number_of_shards', 5 ), // Default within Elasticsearch
            'number_of_replicas' => (int) apply_filters( 'ep_default_index_number_of_replicas', 1 ), // Default within Elasticsearch
        ),
        'analysis' => array(
            'analyzer' => array(
                'default' => array(
                    'tokenizer' => 'standard',
                    'filter' => array( 'standard', 'ewp_word_delimiter', 'lowercase', 'stop', 'ewp_snowball' ),
                    'language' => 'English'
                ),
                'shingle_analyzer' => array(
                    'type' => 'custom',
                    'tokenizer' => 'standard',
                    'filter' => array( 'lowercase', 'shingle_filter' )
                ),
            ),
            'filter' => array(
                'shingle_filter' => array(
                    'type' => 'shingle',
                    'min_shingle_size' => 2,
                    'max_shingle_size' => 5
                ),
                'ewp_word_delimiter' => array(
                    'type' => 'word_delimiter',
                    'preserve_original' => true
                ),
                'ewp_snowball' => array(
                    'type' => 'snowball',
                    'language' => 'English'
                ),
                'edge_ngram' => array(
                    'side' => 'front',
                    'max_gram' => 10,
                    'min_gram' => 3,
                    'type' => 'edgeNGram'
                )
            )
        )
    ),
    'mappings' => array(
        'post' => array(
            "date_detection" => false,
            "dynamic_templates" => array(
                array(
                    "template_meta" => array(
                        "path_match" => "post_meta.*",
                        "mapping" => array(
                            "type" => "multi_field",
                            "path" => "full",
                            "fields" => array(
                                "{name}" => array(
                                    "type" => "string",
                                    "index" => "analyzed"
                                ),
                                "raw" => array(
                                    "type" => "string",
                                    "index" => "not_analyzed",
                                    'include_in_all' => false
                                )
                            )
                        )
                    )
                ),
                array(
                    "template_terms" => array(
                        "path_match" => "terms.*",
                        "mapping" => array(
                            "type" => "object",
                            "path" => "full",
                            "properties" => array(
                                "name" => array(
                                    "type" => "string"
                                ),
                                "term_id" => array(
                                    "type" => "long"
                                ),
                                "parent" => array(
                                    "type" => "long"
                                ),
                                "slug" => array(
                                    "type" => "string",
                                    "index" => "not_analyzed"
                                )
                            )
                        )
                    )
                ),
                array(
                    "term_suggest" => array(
                        "path_match" => "term_suggest_*",
                        "mapping" => array(
                            "type" => "completion",
                            "analyzer" => "default",
                        )
                    )
                )
            ),
            "_all" => array(
                "analyzer" => "simple"
            ),
            'properties' => array(
                'post_id' => array(
                    'type' => 'long',
                    'index' => 'not_analyzed',
                    'include_in_all' => false
                ),
                'post_author' => array(
                    'type' => 'object',
                    'path' => 'full',
                    'fields' => array(
                        'display_name' => array(
                            'type' => 'string',
                            'analyzer' => 'standard',
                        ),
                        'login' => array(
                            'type' => 'string',
                            'analyzer' => 'standard',
                        ),
                        'id' => array(
                            'type' => 'long',
                            'index' => 'not_analyzed'
                        ),
                        'raw' => array(
                            'type' => 'string',
                            'index' => 'not_analyzed',
                            'include_in_all' => false
                        )
                    )
                ),
                'post_date' => array(
                    'type' => 'date',
                    'format' => 'YYYY-MM-dd HH:mm:ss',
                    'include_in_all' => false
                ),
                'post_date_gmt' => array(
                    'type' => 'date',
                    'format' => 'YYYY-MM-dd HH:mm:ss',
                    'include_in_all' => false
                ),
                'post_title' => array(
                    'type' => 'multi_field',
                    'fields' => array(
                        'post_title' => array(
                            'type' => 'string',
                            'analyzer' => 'standard',
                            'store' => 'yes'
                        ),
                        'raw' => array(
                            'type' => 'string',
                            'index' => 'not_analyzed',
                            'include_in_all' => false
                        )
                    )
                ),
                'post_excerpt' => array(
                    'type' => 'string'
                ),
                'post_content' => array(
                    'type' => 'string',
                    'analyzer' => 'default'
                ),
                'post_status' => array(
                    'type' => 'string',
                    'index' => 'not_analyzed'
                ),
                'post_name' => array(
                    'type' => 'multi_field',
                    'fields' => array(
                        'post_name' => array(
                            'type' => 'string'
                        ),
                        'raw' => array(
                            'type' => 'string',
                            'index' => 'not_analyzed',
                            'include_in_all' => false
                        )
                    )
                ),
                'post_modified' => array(
                    'type' => 'date',
                    'format' => 'YYYY-MM-dd HH:mm:ss',
                    'include_in_all' => false
                ),
                'post_modified_gmt' => array(
                    'type' => 'date',
                    'format' => 'YYYY-MM-dd HH:mm:ss',
                    'include_in_all' => false
                ),
                'post_parent' => array(
                    'type' => 'long',
                    'index' => 'not_analyzed',
                    'include_in_all' => false
                ),
                'post_type' => array(
                    'type' => 'multi_field',
                    'fields' => array(
                        'post_type' => array(
                            'type' => 'string'
                        ),
                        'raw' => array(
                            'type' => 'string',
                            'index' => 'not_analyzed',
                            'include_in_all' => false
                        )
                    )
                ),
                'post_mime_type' => array(
                    'type' => 'string',
                    'index' => 'not_analyzed',
                    'include_in_all' => false
                ),
                'permalink' => array(
                    'type' => 'string'
                ),
                'terms' => array(
                    "type" => "object"
                ),
                'post_meta' => array(
                    'type' => 'object'
                )
            )
        )
    )
);
