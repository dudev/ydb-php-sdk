<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Ydb\View\V1;

/**
 */
class ViewServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * @param \Ydb\View\DescribeViewRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function DescribeView(\Ydb\View\DescribeViewRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.View.V1.ViewService/DescribeView',
        $argument,
        ['\Ydb\View\DescribeViewResponse', 'decode'],
        $metadata, $options);
    }

}
