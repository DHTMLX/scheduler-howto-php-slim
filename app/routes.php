<?php
declare(strict_types=1);

use App\Application\Actions\User\ListUsersAction;
use App\Application\Actions\User\ViewUserAction;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

return function (App $app) {
    $app->get('/', function (Request $request, Response $response) {
        $payload = file_get_contents('../app/templates/index.html');
        $response->getBody()->write($payload);
        return $response;
    });
    $app->get('/basic', function (Request $request, Response $response) {
        $payload = file_get_contents('../app/templates/basic.html');
        $response->getBody()->write($payload);
        return $response;
    });
    $app->get('/recurring', function (Request $request, Response $response) {
        $payload = file_get_contents('../app/templates/recurring.html');
        $response->getBody()->write($payload);
        return $response;
    });
    $app->group('/events', function ($group) {
        $group->get('',  function (Request $request, Response $response, array $args) {
            $db = $this->get('PDO');
            $queryText = 'SELECT * FROM `events`';
            $params = $request->getQueryParams();
            $queryParams = [];
            if (isset($params['from']) && isset($params['to'])) {
                $queryText .= " WHERE `end_date`>=? AND `start_date` < ?;";
                $queryParams = [$params['from'], $params['to']];
            }
            $query = $db->prepare($queryText);
            $query->execute($queryParams);
            $result = $query->fetchAll();
            $payload = json_encode($result);

            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        });

        $group->post('', function (Request $request, Response $response, array $args) {
            $db = $this->get('PDO');
            $body = $request->getParsedBody();

            $queryText = 'INSERT INTO `events` SET
                        `start_date`=?,
                        `end_date`=?,
                        `text`=?';
            $queryParams = [
                $body['start_date'],
                $body['end_date'],
                $body['text']
            ];

            $query = $db->prepare($queryText);
            $query->execute($queryParams);
            $result = [
                'tid' => $db->lastInsertId(),
                'action' => $resultAction
            ];

            $payload = json_encode($result);

            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        });


        $group->put('/{id}', function (Request $request, Response $response, array $args) {
            $db = $this->get('PDO');
            $id = $request->getAttribute('route')->getArgument('id');
            parse_str(file_get_contents("php://input"), $body);

            $queryText = 'UPDATE `events` SET
                    `start_date`=?,
                    `end_date`=?,
                    `text`=?
                    WHERE `id`=?';

            $queryParams = [
                $body['start_date'],
                $body['end_date'],
                $body['text'],
                $id
            ];

            $query = $db->prepare($queryText);
            $query->execute($queryParams);

            $result = [
                'action' => 'updated'
            ];
            $payload = json_encode($result);

            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        });
        $group->delete('/{id}', function (Request $request, Response $response, array $args) {
            $db = $this->get('PDO');
            $id = $request->getAttribute('route')->getArgument('id');

            $queryText = 'DELETE FROM `events` WHERE `id`=? ;';

            $query = $db->prepare($queryText);
            $query->execute([$id]);

            $result = [
                'action' => 'deleted'
            ];

            $payload = json_encode($result);

            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        });
    });

    $app->group('/recurring_events', function ($group) {
        $group->get('',  function (Request $request, Response $response, array $args) {
            $db = $this->get('PDO');
            $queryText = 'SELECT * FROM `recurring_events`';
            $params = $request->getQueryParams();
            $queryParams = [];
            if (isset($params['from']) && isset($params['to'])) {
                $queryText .= " WHERE `end_date`>=? AND `start_date` < ?;";
                $queryParams = [$params['from'], $params['to']];
            }
            $query = $db->prepare($queryText);
            $query->execute($queryParams);
            $result = $query->fetchAll();
            $payload = json_encode($result);

            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        });

        $group->post('', function (Request $request, Response $response, array $args) {
            $db = $this->get('PDO');
            $body = $request->getParsedBody();

            $queryText = 'INSERT INTO `recurring_events` SET
                        `start_date`=?,
                        `end_date`=?,
                        `text`=?,
                         `event_pid`=?,
                        `event_length`=?,
                        `rec_type`=?';
            $queryParams = [
                $body['start_date'],
                $body['end_date'],
                $body['text'],
                // recurring events columns
                $body['event_pid'] ? $body['event_pid'] : 0,
                $body['event_length'] ? $body['event_length'] : 0,
                $body['rec_type']
            ];

            // delete a single occurrence from  recurring series
            $resultAction = 'inserted';
            if ($body['rec_type'] === "none") {
                $resultAction = 'deleted';//!
            }
            /*
            end of recurring events data processing
            */

            $query = $db->prepare($queryText);
            $query->execute($queryParams);
            $result = [
                'tid' => $db->lastInsertId(),
                'action' => $resultAction
            ];

            $payload = json_encode($result);

            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        });


        $group->put('/{id}', function (Request $request, Response $response, array $args) {
            $db = $this->get('PDO');
            $id = $request->getAttribute('route')->getArgument('id');
            parse_str(file_get_contents("php://input"), $body);

            $queryText = 'UPDATE `recurring_events` SET
                    `start_date`=?,
                    `end_date`=?,
                    `text`=?,
                    `event_pid`=?,
                    `event_length`=?,
                    `rec_type`=?
                    WHERE `id`=?';

            $queryParams = [
                $body['start_date'],
                $body['end_date'],
                $body['text'],

                $body['event_pid'] ? $body['event_pid'] : 0,
                $body['event_length'] ? $body['event_length'] : 0,
                $body['rec_type'],//!
                $id
            ];
            if ($body['rec_type'] && $body['rec_type'] != 'none') {
                //all modified occurrences must be deleted when you update recurring series
                //https://docs.dhtmlx.com/scheduler/server_integration.html#savingrecurringevents
                  $subQueryText = 'DELETE FROM `recurring_events` WHERE `event_pid`=? ;';
                  $subQuery = $db->prepare($subQueryText);
                  $subQuery->execute([$id]);
            }

            $query = $db->prepare($queryText);
            $query->execute($queryParams);

            $result = [
                'action' => 'updated'
            ];
            $payload = json_encode($result);

            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        });
        $group->delete('/{id}', function (Request $request, Response $response, array $args) {
            $db = $this->get('PDO');
            $id = $request->getAttribute('route')->getArgument('id');
            // some logic specific to recurring events support
            // https://docs.dhtmlx.com/scheduler/server_integration.html#savingrecurringevents
            $subQueryText = 'SELECT * FROM `events` WHERE id=? LIMIT 1;';
            $subQuery = $db->prepare($subQueryText);
            $subQuery->execute([$id]);
            $event = $subQuery->fetch(PDO::FETCH_ASSOC);

            if ($event['event_pid']) {
                // deleting a modified occurrence from a recurring series
                // If an event with the event_pid value was deleted - it needs updating
                // with rec_type==none instead of deleting.
               $subQueryText='UPDATE `recurring_events` SET `rec_type`=\'none\' WHERE `id`=?;';
               $subQuery = $db->prepare($subQueryText);
               $query->execute($queryParams);

                $result = [
                    'action' => 'deleted'
                ];

                $payload = json_encode($result);

                $response->getBody()->write($payload);
                return $response->withHeader('Content-Type', 'application/json');
            }

            if ($event['rec_type'] && $event['rec_type'] != 'none') {//!
                // if a recurring series deleted, delete all modified occurrences of the series
                $subQueryText = 'DELETE FROM `recurring_events` WHERE `event_pid`=? ;';
                $subQuery = $db->prepare($subQueryText);
                $subQuery->execute([$id]);
            }

            /*
             end of recurring events data processing
            */

            $queryText = 'DELETE FROM `recurring_events` WHERE `id`=? ;';

            $query = $db->prepare($queryText);
            $query->execute([$id]);

            $result = [
                'action' => 'deleted'
            ];

            $payload = json_encode($result);

            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        });
    });
};
