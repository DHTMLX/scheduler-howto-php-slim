<?php
use Slim\Http\Request;
use Slim\Http\Response;

// Routes
$app->get('/', function (Request $request, Response $response, array $args) {
	return $this->renderer->render($response, 'index.phtml', $args);
});


$app->get('/basic', function (Request $request, Response $response, array $args) {
	return $this->renderer->render($response, 'basic.phtml', $args);
});

$app->get('/recurring', function (Request $request, Response $response, array $args) {
	return $this->renderer->render($response, 'recurring.phtml', $args);
});


$schedulerApiMiddleware = function ($request, $response, $next) {
	try {
		$response = $next($request, $response);
	} catch (Exception $e) {
		// Reset the response and write an error message
		$response = new \Slim\Http\Response();
		return $response->withJson([
			'action' => 'error',
			'message' => $e->getMessage()
		]);
	}
	return $response;
};

$app->group('/events', function () {
	$this->get('', function (Request $request, Response $response, array $args) {
		$db = $this->database;
		$queryText = 'SELECT * FROM `events`';

		$params = $request->getQueryParams();
		$queryParams = [];

		if (isset($params['from']) && isset($params['to'])) {
			$queryText .= " WHERE `end_date`>=? AND `start_date`<?;";
			$queryParams = [$params['from'], $params['to']];
		}

		$query = $db->prepare($queryText);
		$query->execute($queryParams);
		$result = $query->fetchAll();

		return $response->withJson($result);
	});

	$this->post('', function (Request $request, Response $response, array $args) {
		$db = $this->database;
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

		return $response->withJson($result);
	});

	$this->put('/{id}', function (Request $request, Response $response, array $args) {
		$db = $this->database;
		$id = $request->getAttribute('route')->getArgument('id');
		$body = $request->getParsedBody();

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

		return $response->withJson($result);
	});

	$this->delete('/{id}', function (Request $request, Response $response, array $args) {
		$db = $this->database;
		$id = $request->getAttribute('route')->getArgument('id');
		$queryText = 'DELETE FROM `events` WHERE `id`=? ;';

		$query = $db->prepare($queryText);
		$query->execute([$id]);

		$result = [
			'action' => 'deleted'
		];

		return $response->withJson($result);
	});
})->add($schedulerApiMiddleware);

$app->group('/recurring_events', function () {

	$this->get('', function (Request $request, Response $response, array $args) {
		$db = $this->database;
		$params = $request->getQueryParams();
		$queryText = 'SELECT * FROM `recurring_events`';
		$queryParams = [];

		if (isset($params['from']) && isset($params['to'])) {
			$queryText .= " WHERE `end_date`>=? AND `start_date`<?;";
			$queryParams = [$params['from'], $params['to']];
		}

		$query = $db->prepare($queryText);
		$query->execute($queryParams);
		$result = $query->fetchAll();

		return $response->withJson($result);
	});

	$this->post('', function (Request $request, Response $response, array $args) {
		$db = $this->database;
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
			$resultAction = 'deleted';
		}

		$query = $db->prepare($queryText);
		$query->execute($queryParams);

		$result = [
			'tid' => $db->lastInsertId(),
			'action' => $resultAction
		];

		return $response->withJson($result);
	});

	$this->put('/{id}', function (Request $request, Response $response, array $args) {
		$db = $this->database;

		$id = $request->getAttribute('route')->getArgument('id');
		$body = $request->getParsedBody();

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
			// recurring events columns
			$body['event_pid'] ? $body['event_pid'] : 0,
			$body['event_length'] ? $body['event_length'] : 0,
			$body['rec_type'],
			// end of recurring events columns
			$id
		];

		if ($body['rec_type'] && $body['rec_type'] != 'none') {
			// all modified occurrences must be deleted when we update recurring series
			// https://docs.dhtmlx.com/scheduler/server_integration.html#savingrecurringevents
			$subQueryText = 'DELETE FROM `recurring_events` WHERE `event_pid`=? ;';
			$subQuery = $db->prepare($subQueryText);
			$subQuery->execute([$id]);
		}

		$query = $db->prepare($queryText);
		$query->execute($queryParams);

		$result = [
			'action' => 'updated'
		];

		return $response->withJson($result);
	});

	$this->delete('/{id}', function (Request $request, Response $response, array $args) {
		$db = $this->database;
		$id = $request->getAttribute('route')->getArgument('id');

		// some logic specific to recurring events support
		// https://docs.dhtmlx.com/scheduler/server_integration.html#savingrecurringevents
		$subQueryText = 'SELECT * FROM `recurring_events` WHERE id=? LIMIT 1;';
		$subQuery = $db->prepare($subQueryText);
		$subQuery->execute([$id]);
		$event = $subQuery->fetch(PDO::FETCH_ASSOC);

		if ($event['event_pid']) {
			// deleting modified occurrence from recurring series
			// If an event with the event_pid value was deleted - it needs updating 
			// with rec_type==none instead of deleting.
			$subQueryText = 'UPDATE `recurring_events` SET `rec_type` = \'none\' WHERE `id`=? ;';
			$subQuery = $db->prepare($subQueryText);
			$query->execute($queryParams);

			$result = [
				'action' => 'deleted'
			];

			$response->withJson($result);
			return;
		}

		if ($event['rec_type'] && $event['rec_type'] != 'none') {
			// if a recurring series was deleted - delete all modified occurrences of the series
			$subQueryText = 'DELETE FROM `recurring_events` WHERE `event_pid`=? ;';
			$subQuery = $db->prepare($subQueryText);
			$subQuery->execute([$id]);
		}

		/*
		/ end of recurring events data processing
		*/
		$queryText = 'DELETE FROM `recurring_events` WHERE `id`=? ;';

		$query = $db->prepare($queryText);
		$query->execute([$id]);

		$result = [
			'action' => 'deleted'
		];
	
		return $response->withJson($result);
	});
})->add($schedulerApiMiddleware);