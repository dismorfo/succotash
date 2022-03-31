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

  $bundles = [];

  if ($config['books']) {
    $bundles[] = 'bundle:dlts_book';
  }

  if ($config['photos']) {
    $bundles[] = 'bundle:dlts_photo_set';
  }

  if ($config['maps']) {
    $bundles[] = 'bundle:dlts_map';
  }

  $query = http_build_query(
    [
      'wt' => 'json',
      'query' => $config['query'],
      'rows' => $config['limit'],
      'start' => $start,
      'fq' => '(' . implode(' OR ', $bundles) . ')',
    ]
  );

  $query .= '&fq=ss_language:en';

  if (isset($config['collection'])) {
    $query .= '&fq=sm_collection_identifier:' . $config['collection'];
  }

  $query .= '&fl=' . implode(',', [
    'entity_id',
    'bundle',
    'sm_field_identifier',
    'ss_language',
    'ss_title_long',
    'ss_identifier',
    'ss_noid',
    'sm_collection_label',
    'sm_collection_code',
    'sm_collection_partner_code',
    'sm_collection_partner_label',
  ]);

  // For later on.
  // $query .= '&facet=true&facet.field=bundle_name&facet.field=sm_collection_label&facet.field=sm_publisher&facet.field=iass_pubyear&facet.field=ss_publication_location&facet.field=sm_subject_label&facet.field=sm_vid_Terms';

  $request = Requests::get("$endpoint?$query");

  if (
    $request->success &&
    $request->status_code === 200
  ) {

    $body = json_decode($request->body);

    foreach ($body->docs as $doc) {
      $bundle = bundle($doc->bundle);
      switch ($bundle) {
        case 'books':
        case 'maps':
          $handle = explode('/', $doc->ss_handle);
          $items[] = [
            'nid' => $doc->entity_id,
            'noid' => $doc->ss_noid,
            'type' => $bundle,
            'identifier' => $doc->sm_field_identifier[0],
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
            'title' => $doc->ss_title_long,
            'identifier' => $doc->ss_identifier,
            'noid' => $doc->ss_noid,
            'type' => $bundle,
            'collections' => [
              'code' => $doc->sm_collection_code[0],
              'label' => $doc->sm_collection_label[0],
            ],
            'partners' => [
              'code' => $doc->sm_collection_partner_code[0],
              'label' => $doc->sm_collection_partner_label[0],
            ],
          ];
          break;
      }
    }
  }

  $facets = [];

  // For later.
  // foreach ($body->facet_counts->facet_fields as $key => $field) {
  //   $facets[$key] = [];
  //   foreach ($field as $i => $entry) {
  //     if ($i % 2 == 0) {
  //       $facets[$key][] = [
  //         'label' => $entry,
  //         'count' => $field[$i + 1],
  //       ];
  //     }
  //   }
  // }

  return [
    'items' => $items,
    'start' => $start,
    'limit' => $config['limit'],
    'maxPage' => ceil((int) $body->numFound / $config['limit']),
    'numFound' => (int) $body->numFound,
    'facet' => $facets,
  ];
}

/**
 * Home function.
 */
function home() {

  try {

    $limit = 25;

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
      'books' => true,
      'photos' => true,
      'maps' => true,
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

    if (isset($_GET['books']) && !empty($_GET['books'])) {
      $_books = filter_var($_GET['books'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
      $search[] = "books=$_books";
      $config['books'] = ($_books == 'true') ? true : false;
    }

    if (isset($_GET['photos']) && !empty($_GET['photos'])) {
      $_photos = filter_var($_GET['photos'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
      $search[] = "photos=$_photos";
      $config['photos'] = ($_photos == 'true') ? true : false;
    }

    if (isset($_GET['maps']) && !empty($_GET['maps'])) {
      $_maps = filter_var($_GET['maps'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
      $search[] = "maps=$_maps";
      $config['maps'] = ($_maps == 'true') ? true : false;
    }

    $request = search($config);

    if ($request) {
      // rebuild cache of this request once in a while.
      $collections_cache = $_ENV['content_dir'] . '/collections.json';      
      if (file_exists($collections_cache)) {
        $collections = json_decode(file_get_contents($collections_cache));
      } else {
        $endpoint = $_ENV['endpoint'];
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
      
      $collection_filter = [
        'label' => '',
        'identifier' => '',
      ];

      if ($_collection) {
        $key = array_search($_collection, array_column($collections->response->docs, 'identifier'));
        if ($key) {
          $collection_filter = [
            'label' => $collections->response->docs[$key]->title . ' - ' . $collections->response->docs[$key]->partners[0]->name,
            'identifier' => $collections->response->docs[$key]->identifier,
          ];
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
        'dataList' => $collection_filter,
        'currentPage' => $config['currentPage'],
        'maxPage' => $request['maxPage'],
        'pageRange' => 1,
        'start' => $start,
        'end' => $start + $request['limit'] - 1,
        'numFound' => $request['numFound'],
        'collections' => $collections->response->docs,
        'search' => implode('&', $search),
        'facets' => $request['facet'],
        'filter' => [
          'books' => $config['books'],
          'photos' => $config['photos'],
          'maps' => $config['maps'],
        ]
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
