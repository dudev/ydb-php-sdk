<?php

namespace YdbPlatform\Ydb;

use Psr\Log\LoggerInterface;
use Ydb\View\V1\ViewServiceClient as ServiceClient;

/**
 * Wraps the draft Ydb.View.V1.ViewService (see ydb-api-protos' draft/protos/ydb_view.proto).
 * Only DescribeView is defined upstream so far.
 */
class View
{
    use Traits\RequestTrait;
    use Traits\ParseResultTrait;
    use Traits\LoggerTrait;

    /**
     * @var ServiceClient
     */
    protected $client;

    /**
     * @var array
     */
    protected $meta;

    /**
     * @var string
     */
    protected $database;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param Ydb $ydb
     * @param LoggerInterface|null $logger
     */
    public function __construct(Ydb $ydb, LoggerInterface $logger = null)
    {
        $this->ydb = $ydb;

        $this->client = new ServiceClient($ydb->endpoint(), $ydb->grpcOpts());

        $this->meta = $ydb->meta();

        $this->credentials = $ydb->iam();

        $this->database = $ydb->database();

        $this->logger = $logger;
    }

    /**
     * @param string $path
     * @return array|mixed|null
     */
    public function describeView($path = '')
    {
        $result = $this->request('DescribeView', [
            'path' => rtrim($this->database . '/' . $path, '/'),
        ]);

        return $this->parseResult($result, ['self', 'query_text']);
    }

    /**
     * @param string $method
     * @param array $data
     * @return bool|mixed|void|null
     * @throws \YdbPlatform\Ydb\Exception
     */
    protected function request($method, array $data = [])
    {
        return $this->doRequest('View', $method, $data);
    }
}
