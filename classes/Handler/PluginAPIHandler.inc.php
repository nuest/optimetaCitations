<?php
/**
 * @file plugins/generic/optimetaCitations/classes/Handler/PluginAPIHandler.inc.php
 *
 * Copyright (c) 2021+ TIB Hannover
 * Copyright (c) 2021+ Gazi Yucel
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PluginAPIHandler
 * @ingroup Handler
 *
 * @brief Extended class of base request API handler
 *
 */
namespace Optimeta\Citations\Handler;

import('lib.pkp.classes.security.authorization.PolicySet');
import('lib.pkp.classes.security.authorization.RoleBasedHandlerOperationPolicy');
import('plugins.generic.optimetaCitations.classes.Enricher.Enricher');
import('plugins.generic.optimetaCitations.classes.Submitter.Submitter');

use APIHandler;
use Optimeta\Citations\Submitter\Submitter;
use RoleBasedHandlerOperationPolicy;
use PolicySet;
use GuzzleHttp\Exception\GuzzleException;
use Optimeta\Citations\Parser\Parser;
use Optimeta\Citations\Enricher\Enricher;

class PluginAPIHandler extends APIHandler
{
    private $apiEndpoint = OPTIMETA_CITATIONS_API_ENDPOINT;

    private $responseBody = [
        'status' => 'ok',
        'message-type' => '',
        'message-version' => '1',
        'message' => ''
    ];

    public function __construct()
    {
        $this->_handlerPath = $this->apiEndpoint;

        $this->_endpoints = [
            'POST' => [
                [
                    'pattern' => $this->getEndpointPattern() . '/process',
                    'handler' => [$this, 'process'],
                    'roles' => [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_REVIEWER, ROLE_ID_AUTHOR],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/submit',
                    'handler' => [$this, 'submit'],
                    'roles' => [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_REVIEWER, ROLE_ID_AUTHOR],
                ]
            ],
            'GET' => []
        ];

        parent::__construct();
    }

    /**
     * @copydoc APIHandler::authorize
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $rolePolicy = new PolicySet(COMBINING_PERMIT_OVERRIDES);

        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }
        $this->addPolicy($rolePolicy);

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * @desc Parse and enrich citations and return
     * @param $slimeRequest
     * @param $response
     * @param $args
     * @return mixed
     * @throws GuzzleException
     */
    public function process($slimeRequest, $response, $args)
    {
        $request = $this->getRequest();
        $submissionId = '';
        $citationsRaw = '';
        $citationsOut = [];

        $this->responseBody['message-type'] = 'citations';

        // check if GET/POST filled
        if ($request->getUserVars() && sizeof($request->getUserVars()) > 0) {
            if(isset($request->getUserVars()['submissionId'])){
                $submissionId = trim($request->getUserVars()['submissionId']);
            }
            if(isset($request->getUserVars()['citationsRaw'])){
                $citationsRaw = trim($request->getUserVars()['citationsRaw']);
            }
        }

        // citationsRaw empty, response empty array
        if (strlen($citationsRaw) === 0) {
            return $response->withJson($this->responseBody, 200);
        }

        // parse citations
        $parser = new Parser();
        $citationsOut = $parser->executeAndReturnCitations($citationsRaw);

        // enrich citations
        $enricher = new Enricher();
        $citationsOut = $enricher->executeAndReturnCitations($citationsOut);

        $this->responseBody['message'] = $citationsOut;
        return $response->withJson($this->responseBody, 200);
    }

    /**
     * @desc
     * @param $slimeRequest
     * @param $response
     * @param $args
     * @return mixed
     * @throws GuzzleException
     */
    public function submit($slimeRequest, $response, $args)
    {
        $request = $this->getRequest();
        $submissionId = '';
        $citationsIn = [];
        $citationsOut = [];

        $this->responseBody['message-type'] = 'citations';

        // check if GET/POST filled
        if ($request->getUserVars() && sizeof($request->getUserVars()) > 0) {
            if(isset($request->getUserVars()['submissionId'])){
                $submissionId = trim($request->getUserVars()['submissionId']);
            }
            if(isset($request->getUserVars()['citations'])){
                $citationsIn = json_decode(trim($request->getUserVars()['citations']), true);
            }
        }

        // citations empty, response empty array
        if (empty($citationsIn)) {
            return $response->withJson($this->responseBody, 200);
        }

        // submit citations
        $submitter = new Submitter();
        $citationsOut = $submitter->executeAndReturnCitations($submissionId, $citationsIn);

        $this->responseBody['message'] = $citationsOut;
        return $response->withJson($this->responseBody, 200);
    }
}