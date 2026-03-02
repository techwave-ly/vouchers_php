<?php

namespace Commerce\Vouchers\Tests;

use PHPUnit\Framework\TestCase;
use Commerce\Vouchers\VouchersClient;
use Commerce\Vouchers\Exceptions\APIError;

class VouchersClientTest extends TestCase
{
    public function testInitialization()
    {
        $client = new VouchersClient('API_KEY_ID', 'API_SECRET', 'https://api.wavecommerce.ly');
        $this->assertInstanceOf(VouchersClient::class, $client);
    }

    public function testMissingApiKeyThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        new VouchersClient('', 'API_SECRET', 'https://api.wavecommerce.ly');
    }

    public function testMissingApiSecretThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        new VouchersClient('API_KEY_ID', '', 'https://api.wavecommerce.ly');
    }
    
    public function testMissingBaseUrlThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        new VouchersClient('API_KEY_ID', 'API_SECRET', '');
    }

    private function getClientMock()
    {
        return $this->getMockBuilder(VouchersClient::class)
            ->setConstructorArgs(['test_key', 'test_secret', 'https://api.wavecommerce.ly'])
            ->onlyMethods(['request'])
            ->getMock();
    }

    public function testSwitchModeSuccess()
    {
        $client = $this->getClientMock();
        $client->expects($this->once())
               ->method('request')
               ->with('POST', '/api/partner/v1/mode', ['mode' => 'test'], null)
               ->willReturn(['mode' => 'test']);

        $response = $client->switchMode('test');
        $this->assertEquals('test', $response['mode']);
    }

    public function testSwitchModeInvalidThrowsException()
    {
        $client = $this->getClientMock();
        $this->expectException(\InvalidArgumentException::class);
        $client->switchMode('invalid');
    }

    public function testIssueVoucherWithoutOptions()
    {
        $client = $this->getClientMock();
        $expectedPayload = [
            'amount' => 100.0,
            'currency' => 'LYD'
        ];

        $client->expects($this->once())
               ->method('request')
               ->with('POST', '/api/partner/v1/vouchers/issue', $expectedPayload, null)
               ->willReturn(['voucher' => ['id' => '123', 'code' => 'TESTCODE']]);

        $response = $client->issueVoucher(100.0);
        $this->assertEquals('123', $response['voucher']['id']);
    }

    public function testIssueVoucherWithOptions()
    {
        $client = $this->getClientMock();
        $expectedPayload = [
            'amount' => 150.0,
            'currency' => 'LYD',
            'campaignId' => 'camp_123',
            'expiresAt' => '2027-12-31T23:59:59Z'
        ];

        $client->expects($this->once())
               ->method('request')
               ->with('POST', '/api/partner/v1/vouchers/issue', $expectedPayload, 'idem-key-1')
               ->willReturn(['voucher' => ['id' => '456', 'code' => 'TESTCODE']]);

        $response = $client->issueVoucher(150.0, [
            'campaignId' => 'camp_123',
            'expiresAt' => '2027-12-31T23:59:59Z',
            'idempotencyKey' => 'idem-key-1'
        ]);
        
        $this->assertEquals('456', $response['voucher']['id']);
    }

    public function testBulkIssueVouchers()
    {
        $client = $this->getClientMock();
        $expectedPayload = [
            'amount' => 50.0,
            'currency' => 'LYD',
            'count' => 10
        ];

        $client->expects($this->once())
               ->method('request')
               ->with('POST', '/api/partner/v1/vouchers/bulk-issue', $expectedPayload, null)
               ->willReturn(['vouchers' => [['id' => '1'], ['id' => '2']]]);

        $response = $client->bulkIssueVouchers(50.0, 10);
        $this->assertCount(2, $response['vouchers']);
    }

    public function testVoidVoucher()
    {
        $client = $this->getClientMock();

        $client->expects($this->once())
               ->method('request')
               ->with('POST', '/api/partner/v1/vouchers/void', ['voucherId' => 'vouch_123'], null)
               ->willReturn(['voucherId' => 'vouch_123']);

        $response = $client->voidVoucher('vouch_123');
        $this->assertEquals('vouch_123', $response['voucherId']);
    }

    public function testGetVoucherStatus()
    {
        $client = $this->getClientMock();

        $client->expects($this->once())
               ->method('request')
               ->with('GET', '/api/partner/v1/vouchers/vouch_123/status', null, null)
               ->willReturn(['status' => 'active', 'isTest' => true]);

        $response = $client->getVoucherStatus('vouch_123');
        $this->assertEquals('active', $response['status']);
        $this->assertTrue($response['isTest']);
    }
}
