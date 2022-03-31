<?php

function select_by_noid($noid, $lang = 'en', $reset_cache = false) {
  $cache = $_ENV['content_dir'] . '/noid.' . $noid . '.' . $lang . '.json';
  $item = [];
  // cache exists and no need to reset request.
  if (!$reset_cache && file_exists($cache)) {
    return json_decode(file_get_contents($cache));
  } else { // cache does not exists or to reset requested.
    $endpoint = $_ENV['discovery'];
    $query = http_build_query(
      [ 
        'wt' => 'json',
        'rows' => 1,
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
        'collections' => (object) [
          'code' => $doc->sm_collection_code[0],
          'label' => $doc->sm_collection_label[0],
          'identifier' => $doc->sm_collection_identifier[0],
        ],
        'partners' => (object) [
          'code' => $doc->sm_collection_partner_code[0],
          'label' => $doc->sm_collection_partner_label[0],
          'identifier' => $doc->sm_collection_partner_identifier[0],
        ],
        'language' => $doc->ss_language,
      ];
      file_put_contents($cache, json_encode($item), LOCK_EX);   
      return (object) $item;
    } else {
      throw new Exception('Not found - Request failed.');
    }
  } 
  return (object) $item;  
}
