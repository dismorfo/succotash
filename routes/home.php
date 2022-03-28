<?php

/**
 * @file
 * home.php
 */

function search($config) {

  $items = [];

  $endpoint = $_ENV['discovery'];

  if ($config['start'] == 1) {
    $start = 0;
  } else {
    $start = $config['start'];
  }
 
  $query = http_build_query(
    [
      'wt' => 'json',
      'query' => $config['query'],
      'rows' => $config['limit'],
      'start' => $start,
      // 'fq' => '(bundle:dlts_book OR bundle:dlts_photo_set OR bundle:dlts_map)',
      'fq' => '(bundle:dlts_book)',
    ]
  );

  $query .= '&fq=ss_language:en';

  if (isset($config['collection'])) {
    $query .= '&fq=sm_collection_identifier:' . $config['collection'];
  }

  $query .= '&fl=' . implode(',', [
    'entity_id',
    'bundle',
    'sm_field_title',
    'sm_field_identifier',
    'ss_language',
    'ss_title_long',
    'ss_book_identifier',
    'ss_handle',
    'sm_collection_label',
    'sm_collection_code',
    'sm_collection_partner_code',
    'sm_collection_partner_label',
  ]);

  $query .= '&facet=true&facet.field=bundle_name&facet.field=sm_collection_label&facet.field=sm_publisher&facet.field=iass_pubyear&facet.field=ss_publication_location&facet.field=sm_subject_label&facet.field=sm_vid_Terms';

  $request = Requests::get("$endpoint?$query");

  if (
    $request->success &&
    $request->status_code === 200
  ) {

    $body = json_decode($request->body);

    foreach ($body->response->docs as $doc) {
      $bundle = bundle($doc->bundle);
      switch ($bundle) {
        case 'books':
          $handle = explode('/', $doc->ss_handle);
          $items[] = [
            'nid' => $doc->entity_id,
            'noid' => $handle[count($handle) - 1],
            'type' => $bundle,
            'identifier' => $doc->ss_book_identifier,
            'title' => $doc->ss_title_long,
            'collections' => [
              'code' => $doc->sm_collection_code[0],
              'label' => $doc->sm_collection_label[0],
            ],
            'partners' => [
              'code' => $doc->sm_collection_partner_code[0],
              'label' => $doc->sm_collection_partner_label[0],
            ],
            'language' => $doc->ss_language,
          ];
          break;
        case 'photos':
          $items[] = [
            'nid' => $doc->entity_id,
            'language' => $doc->ss_language,
            'title' => $doc->sm_field_title[0],
            'identifier' => $doc->sm_field_identifier[0],
            'noid' => $doc->sm_field_identifier[0],
            'type' => $bundle,
          ];
          break;
      }
    }
  }

  $facets = [];

  foreach ($body->facet_counts->facet_fields as $key => $field) {
    $facets[$key] = [];
    foreach ($field as $i => $entry) {
      if ($i % 2 == 0) {
        $facets[$key][] = [
          'label' => $entry,
          'count' => $field[$i + 1],
        ];
      }
    }
  }

  return [
    'items' => $items,
    'start' => $start,
    'limit' => $config['limit'],
    'maxPage' => ceil((int) $body->response->numFound / $config['limit']),
    'numFound' => (int) $body->response->numFound,
    'facet' => $facets,
  ];
}

/**
 * Home function.
 */
function home() {

  try {

    $limit = 10;

    $raw_page = isset($_GET['page']) ? $_GET['page'] : 1;
   
    $start = $raw_page * $limit - $limit + 1;

    if ($start == 1) {
      $start = 0;
    }

    $search = [];

    $config = [
      'start' => $start,
      'limit' => $limit,
      'currentPage' => filter_var($raw_page, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW),
    ];

    if (isset($_GET['query']) && !empty($_GET['query'])) {
      $_query = filter_var($_GET['query'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
      $search[] = "query=$_query";
      $config = array_merge($config, ['query' => $_query]);
    }

    if (isset($_GET['collection']) && !empty($_GET['collection'])) {
      $_collection = filter_var($_GET['collection'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
      $search[] = "collection=$_collection";
      $config = array_merge($config, ['collection' => $_collection]);
    }

    $request = search($config);

    if ($request) {
      // rebuild cache of this request once in a while.
      $collections_cache = $_ENV['content_dir'] . '/collections.json';      
      if (file_exists($collections_cache)) {
        $collections = json_decode(file_get_contents($collections_cache));
      } else {
        $request_collections = Requests::get("$endpoint/api/v1/collections");
        if (
          $request_collections->success &&
          $request_collections->status_code === 200
        ) {
          file_put_contents($collections_cache, $request_collections->body, LOCK_EX);
          $collections = json_decode($request_collections->body);
        } else {
          throw new Exception('Unable to request collections - Request failed.');
        }
      }
      
      if ($_collection) {
        $key = array_search($_collection, array_column($collections->response->docs, 'identifier'));
        if ($key) {
          $collections->response->docs[$key]->selected = true;
        }        
      }
    }

    return [
      'template' => 'home.html',
      'data' => [
        'title' => 'Home',
        'items' => $request['items'],
        'pageLimit' => $request['limit'],
        'currentPage' => $config['currentPage'],
        'maxPage' => $request['maxPage'],
        'pageRange' => 1,
        'start' => $start,
        'end' => $start + $request['limit'] - 1,
        'numFound' => $request['numFound'],
        'collections' => $collections->response->docs,
        'search' => implode('&', $search),
        'facets' => $request['facet'],
      ],
    ];
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
