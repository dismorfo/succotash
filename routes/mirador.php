<?php

/**
 * @file
 * mirador.php
 */

function search_by_noid($noid, $lang = 'en') {

  $item = [];

  $endpoint = $_ENV['discovery'];

  $query = http_build_query(
    [
      'wt' => 'json',
      // 'rows' => 1,
      'start' => 0,
      'fq' => "ss_noid:$noid",
    ]
  );

  $query .= "&fq=ss_language:$lang";

  $query .= '&fl=' . implode(',', [
    'entity_id',
    'bundle',
    'sm_field_identifier',
    'ss_language',
    'ss_title_long',
    'ss_identifier',
    'ss_noid',
    'ss_manifest',
    'sm_collection_label',
    'sm_collection_code',
    'sm_collection_identifier',
    'sm_collection_partner_identifier',
    'sm_collection_partner_code',
    'sm_collection_partner_label',
  ]);

  $request = Requests::get("$endpoint?$query");

  if (
    $request->success &&
    $request->status_code === 200
  ) {

    $body = json_decode($request->body);

    $doc = $body->docs[0];

    $item = [
      'nid' => $doc->entity_id,
      'noid' => $doc->ss_noid,
      'manifest' => $doc->ss_manifest,
      'type' => bundle($doc->bundle),
      'identifier' => $doc->sm_field_identifier[0],
      'title' => $doc->ss_title_long,
      'collections' => [
        'code' => $doc->sm_collection_code[0],
        'label' => $doc->sm_collection_label[0],
        'identifier' => $doc->sm_collection_identifier[0],
      ],
      'partners' => [
        'code' => $doc->sm_collection_partner_code[0],
        'label' => $doc->sm_collection_partner_label[0],
        'identifier' => $doc->sm_collection_partner_identifier[0],
      ],
      'language' => $doc->ss_language,
    ];
  }

  return $item;

}

/**
 * Mirador.
 */
function mirador($args) {
  try {

    $noid = filter_var(
      $args[0],
      FILTER_UNSAFE_RAW,
      FILTER_FLAG_STRIP_LOW
    );

    $lang = 'en';

    $cache = $_ENV['content_dir'] . '/' . $noid . '.' . $lang . '.json';

    if (file_exists($cache)) {
      $request = json_decode(file_get_contents($cache));
    } else {
      $request = search_by_noid($noid);
      if ($request) {
        file_put_contents($cache, json_encode($request), LOCK_EX);
      } else {
        throw new Exception('Not found - Request failed.');
      }
    }
    if ($request) {
      $data = [
        'nid' => $request->$nid,
        'noid' => $request->$noid,
        'title' => $request->title,
        'manifest' => $request->manifest,
        // 'availableLanguages' => $body->availableLanguages,        
        'description' => '',
        'summary' => '',
        'og_summary' => '',
      ];
      return [
        'template' => 'mirador.html',
        'data' => $data,
      ];
    }
    else {
      throw new Exception('Viewer request failed.');
    }
  }
  catch (Exception $e) {
    return [
      'template' => 'error.html',
      'data' => [
        'title' => 'Error',
        'body' => $e->getMessage(),
      ],
    ];
  }
}
