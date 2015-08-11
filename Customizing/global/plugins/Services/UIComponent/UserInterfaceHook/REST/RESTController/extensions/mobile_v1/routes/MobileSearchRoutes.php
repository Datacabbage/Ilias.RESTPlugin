<?php
/**
 * ILIAS REST Plugin for the ILIAS LMS
 *
 * Authors: D.Schaefer and T.Hufschmidt <(schaefer|hufschmidt)@hrz.uni-marburg.de>
 * Since 2014
 */
namespace RESTController\extensions\mobile_v1;


$app->group('v1/m','\RESTController\libs\OAuth2Middleware::TokenRouteAuth', function () use ($app) {
    $app->get('/search/',  function () use ($app) {
        $response = $app->request();

        try {
            $query = utf8_encode($request->params('q'));
        } catch (\Exception $e) {
            $query = '';
        }

        $model = new MobileSearchModel();
        $searchResults = $model->performSearch($query);

        $app->success($searchResults);
    });

    $app->post('/search/', '\RESTController\libs\OAuth2Middleware::TokenRouteAuth', function () use ($app) {
        $response = $app->request();

        try {
            $query = utf8_encode($request->params('q'));
        } catch (\Exception $e) {
            $query = '';
        }

        $model = new MobileSearchModel();
        $searchResults = $model->performSearch($query);

        $app->success($searchResults);
    });
});
