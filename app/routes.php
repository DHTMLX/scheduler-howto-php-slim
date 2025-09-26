<?php
declare(strict_types=1);

use App\Application\Actions\User\ListUsersAction;
use App\Application\Actions\User\ViewUserAction;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    $app->get('/', function (Request $request, Response $response) {
        $filePath = __DIR__ . '/templates/index.html';
        $payload = file_get_contents($filePath);
        $response->getBody()->write($payload);
        return $response;
    });
    $app->get('/basic', function (Request $request, Response $response) {
        $filePath = __DIR__ . '/templates/basic.html';
        $payload = file_get_contents($filePath);
        $response->getBody()->write($payload);
        return $response;
    });
    $app->get('/recurring', function (Request $request, Response $response) {
        $filePath = __DIR__ . '/templates/recurring.html';
        $payload = file_get_contents($filePath);
        $response->getBody()->write($payload);
        return $response;
    });
    $app->group('/events', function (RouteCollectorProxy $group) {
        $group->get('',  function (Request $request, Response $response, array $args) {
            $db = $this->get(PDO::class);
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
            $db = $this->get(PDO::class);
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
                'action' => 'inserted'
            ];

            $payload = json_encode($result);

            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        });


        $group->put('/{id}', function (Request $request, Response $response, array $args) {
            $db = $this->get(PDO::class);
            $id = $args['id'];
            $body = $request->getParsedBody();
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

            $result = ['action' => 'updated'];
            $payload = json_encode($result);

            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        });

        $group->delete('/{id}', function (Request $request, Response $response, array $args) {
            $db = $this->get(PDO::class);
            $id = $args['id'];

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
            $db = $this->get(PDO::class);
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
            $db = $this->get(PDO::class);
            $body = $request->getParsedBody();

            $queryText = "INSERT INTO `recurring_events` SET
                `start_date`=?,
                `end_date`=?,
                `text`=?,
                `duration`=?,
                `rrule`=?,
                `recurring_event_id`=?,
                `original_start`=?,
                `deleted`=?";
            $queryParams = [
                $body["start_date"],
                $body["end_date"],
                $body["text"],
                $body["duration"] ? $body["duration"] : null,
                $body["rrule"] ? $body["rrule"] : null,
                $body["recurring_event_id"] ? $body["recurring_event_id"] : null,
                $body["original_start"] ? $body["original_start"] : null,
                (isset($body["deleted"]) && $body["deleted"] === "true") ? 1 :
                ((isset($body["deleted"]) && $body["deleted"] === "false") ? 0 : null)
            ];
            // delete a single occurrence from recurring series
            $resultAction = 'inserted'; 
            if (isset($body["deleted"]) && $body["deleted"] === "true") { 
                $resultAction = 'deleted'; 
            } 
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
            $db = $this->get(PDO::class);
            $id = $args['id'];
            $body = $request->getParsedBody();
            parse_str(file_get_contents("php://input"), $body);
            $queryText = "UPDATE `recurring_events` SET
                `start_date`=?,
                `end_date`=?,
                `text`=?,
                `duration`=?,
                `rrule`=?,
                `recurring_event_id`=?,
                `original_start`=?,
                `deleted`=?
                WHERE `id`=?";
            $queryParams = [
                $body["start_date"],
                $body["end_date"],
                $body["text"],
                $body["duration"] ? $body["duration"] : null,
                $body["rrule"] ? $body["rrule"] : null,
                $body["recurring_event_id"] ? $body["recurring_event_id"] : null,
                $body["original_start"] ? $body["original_start"] : null,
                $body["deleted"] ? $body["deleted"] : null,
                $id
            ];

            if ($body["rrule"] && $body["recurring_event_id"] == null) {
                //all modified occurrences must be deleted when you update recurring  series
                //https://docs.dhtmlx.com/scheduler/server_integration.html#recurringevents
                $subQueryText = "DELETE FROM `recurring_events` WHERE `recurring_event_id`=? ;";
                $subQuery = $db->prepare($subQueryText);
                $subQuery->execute([$id]);
            }

            $query = $db->prepare($queryText);
            $query->execute($queryParams);

            $result = ['action' => 'updated'];
            $payload = json_encode($result);

            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        });

        $group->delete('/{id}', function (Request $request, Response $response, array $args) {
            $db = $this->get(PDO::class);
            $id = $args['id'];

            // Fetch the event
            $subQueryText = "SELECT * FROM `recurring_events` WHERE `id` = ? LIMIT 1;";
            $subQuery = $db->prepare($subQueryText);
            $subQuery->execute([$id]);
            $event = $subQuery->fetch(PDO::FETCH_ASSOC);

            if ($event && $event["recurring_event_id"]) {
                // Modified occurrence of a recurring event - mark as deleted
                $updateQueryText = "UPDATE `recurring_events` SET `deleted` = 1 WHERE `id` = ?;";
                $updateQuery = $db->prepare($updateQueryText);
                $updateQuery->execute([$id]);
            } else {
                if ($event && $event["rrule"]) {
                    // Deleting recurring series - delete all modified occurrences as well
                    $deleteModifiedOccurrencesQueryText = "DELETE FROM `recurring_events` WHERE `recurring_event_id` = ?;";
                    $deleteModifiedOccurrencesQuery = $db->prepare($deleteModifiedOccurrencesQueryText);
                    $deleteModifiedOccurrencesQuery->execute([$id]);
                }
                // Delete the event itself
                $deleteQueryText = "DELETE FROM `recurring_events` WHERE `id` = ?;";
                $deleteQuery = $db->prepare($deleteQueryText);
                $deleteQuery->execute([$id]);
            }

            $result = ['action' => 'deleted'];
            $payload = json_encode($result);

            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        });
    });
};
