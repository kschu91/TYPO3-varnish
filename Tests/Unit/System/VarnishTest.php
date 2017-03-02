<?php
namespace Aoe\Varnish\System;

use Aoe\Varnish\Domain\Model\TagInterface;
use Aoe\Varnish\TYPO3\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogManager;

/**
 * @covers \Aoe\Varnish\System\Varnish
 */
class VarnishTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Varnish
     */
    private $varnish;

    /**
     * @var Http|\PHPUnit_Framework_MockObject_MockObject
     */
    private $http;

    /**
     * @var ExtensionConfiguration|\PHPUnit_Framework_MockObject_MockObject
     */
    private $extensionConfiguration;

    /**
     * @var LogManager|\PHPUnit_Framework_MockObject_MockObject
     */
    private $logManager;

    public function setUp()
    {
        $this->http = $this->getMockBuilder(Http::class)
            ->setMethods(array('request', 'wait'))
            ->disableOriginalConstructor()
            ->getMock();

        $this->extensionConfiguration = $this->getMockBuilder(ExtensionConfiguration::class)
            ->setMethods(['getHosts', 'getBanTimeout', 'getDefaultTimeout'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->extensionConfiguration
            ->expects($this->any())
            ->method('getHosts')
            ->willReturn(['domain.tld']);
        $this->extensionConfiguration
            ->expects($this->any())
            ->method('getBanTimeout')
            ->willReturn(10);
        $this->extensionConfiguration
            ->expects($this->any())
            ->method('getDefaultTimeout')
            ->willReturn(0);

        $this->logManager = $this->getMockBuilder(LogManager::class)
            ->disableOriginalConstructor()
            ->setMethods(['getLogger'])
            ->getMock();

        $this->varnish = new Varnish($this->http, $this->extensionConfiguration, $this->logManager);
    }

    /**
     * @test
     *
     * @expectedException \RuntimeException
     * @expectedExceptionCode 1435159558
     */
    public function banByTagShouldThrowExceptionOnInvalidTag()
    {
        $tag = $this->getMockBuilder(TagInterface::class)
            ->setMethods(array('isValid', 'getIdentifier'))
            ->getMock();
        $tag->expects($this->once())->method('isValid')->willReturn(false);
        /** @var TagInterface $tag */
        $this->varnish->banByTag($tag);
    }

    /**
     * @test
     */
    public function banByTagShouldCallHttpCorrectly()
    {
        $this->http->expects($this->once())->method('request')->with(
            'BAN',
            'domain.tld',
            ['X-Ban-Tags' => 'my_identifier'],
            10
        );
        /** @var TagInterface|\PHPUnit_Framework_MockObject_MockObject $tag */
        $tag = $this->getMockBuilder(TagInterface::class)
            ->setMethods(array('isValid', 'getIdentifier'))
            ->getMock();
        $tag->expects($this->once())->method('isValid')->willReturn(true);
        $tag->expects($this->once())->method('getIdentifier')->willReturn('my_identifier');
        $this->varnish->banByTag($tag);
    }

    /**
     * @test
     */
    public function banAllShouldCallHttpCorrectly()
    {
        $this->http->expects($this->once())->method('request')->with('BAN', 'domain.tld', ['X-Ban-All' => '1'], 10);
        $this->varnish->banAll();
    }

    /**
     * @test
     */
    public function shouldLogOnShutdown()
    {
        $this->http->expects($this->once())->method('wait')->willReturn([
            ['success' => true, 'reason' => 'banned all'],
            ['success' => false, 'reason' => 'failed!']
        ]);

        $logger = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->setMethods(['info', 'alert'])
            ->getMock();
        $logger->expects($this->once())->method('info')->with('banned all');
        $logger->expects($this->once())->method('alert')->with('failed!');

        $this->logManager->expects($this->any())->method('getLogger')
            ->willReturn($logger);

        $this->varnish->shutdown();
    }
}
