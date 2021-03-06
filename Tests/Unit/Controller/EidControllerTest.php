<?php
declare(strict_types = 1);
namespace Tx\Tinyurls\Tests\Unit\Controller;

/*                                                                        *
 * This script belongs to the TYPO3 extension "tinyurls".                 *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use PHPUnit\Framework\TestCase;
use Tx\Tinyurls\Controller\EidController;
use Tx\Tinyurls\Domain\Model\TinyUrl;
use Tx\Tinyurls\Domain\Repository\TinyUrlRepository;
use Tx\Tinyurls\Utils\HttpUtilityWrapper;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * @backupGlobals enabled
 */
class EidControllerTest extends TestCase
{
    /**
     * @var EidController
     */
    protected $eidController;

    /**
     * @var HttpUtilityWrapper|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $httpUtilityMock;

    /**
     * @var TinyUrlRepository|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $tinyUrlRepositoryMock;

    /**
     * @var TypoScriptFrontendController|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $tsfeMock;

    protected function setUp()
    {
        $this->tsfeMock = $this->createMock(TypoScriptFrontendController::class);
        $this->httpUtilityMock = $this->createMock(HttpUtilityWrapper::class);
        $this->tinyUrlRepositoryMock = $this->createMock(TinyUrlRepository::class);

        $this->eidController = new EidController();
        $this->eidController->setTypoScriptFrontendController($this->tsfeMock);
        $this->eidController->injectHttpUility($this->httpUtilityMock);
        $this->eidController->injectTinyUrlRepository($this->tinyUrlRepositoryMock);
    }

    public function testDeleteOnUseUrlIsDeleted()
    {
        $_GET['tx_tinyurls']['key'] = 'thekey';
        $tinyUrlMock = $this->createMock(TinyUrl::class);
        $tinyUrlMock->method('getDeleteOnUse')->willReturn(true);

        $this->tinyUrlRepositoryMock->expects($this->once())
            ->method('findTinyUrlByKey')
            ->willReturn($tinyUrlMock);

        $this->tinyUrlRepositoryMock->expects($this->once())
            ->method('deleteTinyUrlByKey')
            ->with('thekey');

        $this->eidController->main();
    }

    public function testDeleteOnUseUrlSendsNoCacheHeaders()
    {
        $_GET['tx_tinyurls']['key'] = 'thekey';
        $tinyUrlMock = $this->createMock(TinyUrl::class);
        $tinyUrlMock->method('getDeleteOnUse')->willReturn(true);

        $this->tinyUrlRepositoryMock->expects($this->once())
            ->method('findTinyUrlByKey')
            ->willReturn($tinyUrlMock);

        $this->httpUtilityMock->expects($this->exactly(4))
            ->method('header')
            ->withConsecutive(
                [
                    'Expires',
                    '0',
                ],
                [
                    'Last-Modified',
                    gmdate('D, d M Y H:i:s') . ' GMT',
                ],
                [
                    'Cache-Control',
                    'no-cache, must-revalidate',
                ],
                [
                    'Pragma',
                    'no-cache',
                ]
            );

        $this->eidController->main();
    }

    public function testHitIsCountedIfUrlIsNotDeletedOnUse()
    {
        $_GET['tx_tinyurls']['key'] = 'thekey';
        $tinyUrlMock = $this->createMock(TinyUrl::class);
        $tinyUrlMock->method('getUid')->willReturn(999);
        $tinyUrlMock->method('getDeleteOnUse')->willReturn(false);

        $this->tinyUrlRepositoryMock->expects($this->once())
            ->method('findTinyUrlByKey')
            ->willReturn($tinyUrlMock);

        $this->tinyUrlRepositoryMock->expects($this->once())
            ->method('countTinyUrlHit')
            ->with($tinyUrlMock);

        $this->eidController->main();
    }

    public function testHitIsNotCountedIfUrlIsDeletedOnUse()
    {
        $_GET['tx_tinyurls']['key'] = 'thekey';
        $tinyUrlMock = $this->createMock(TinyUrl::class);
        $tinyUrlMock->method('getDeleteOnUse')->willReturn(true);

        $this->tinyUrlRepositoryMock->expects($this->once())
            ->method('findTinyUrlByKey')
            ->willReturn($tinyUrlMock);

        $this->tinyUrlRepositoryMock->expects($this->never())
            ->method('countTinyUrlHit');

        $this->eidController->main();
    }

    public function testInvalidUrlsArePurgedBeforeRedirect()
    {
        $this->tinyUrlRepositoryMock->expects($this->once())
            ->method('purgeInvalidUrls');

        $this->eidController->main();
    }

    public function testPageNotFoundErrorIfNoUrlKeyIsProvided()
    {
        $this->tsfeMock->expects($this->once())
            ->method('pageNotFoundAndExit')
            ->with('No tinyurl key was submitted.');

        $this->eidController->main();
    }

    public function testPageNotFoundErrorIfUrlKeyIsNotFoundInDatabase()
    {
        $_GET['tx_tinyurls']['key'] = 'thekey';

        $this->tinyUrlRepositoryMock->expects($this->once())
            ->method('findTinyUrlByKey')
            ->with('thekey')
            ->willThrowException(new \RuntimeException('No tinyurl found with this key'));

        $this->tsfeMock->expects($this->once())
            ->method('pageNotFoundAndExit')
            ->with('No tinyurl found with this key');

        $this->eidController->main();
    }

    public function testRedirectsToTargetUrl()
    {
        $_GET['tx_tinyurls']['key'] = 'thekey';
        $tinyUrlMock = $this->createMock(TinyUrl::class);
        $tinyUrlMock->method('getUid')->willReturn(666);
        $tinyUrlMock->method('getTargetUrl')->willReturn('http://the-target.url');
        $tinyUrlMock->method('getDeleteOnUse')->willReturn(false);

        $this->tinyUrlRepositoryMock->expects($this->once())
            ->method('findTinyUrlByKey')
            ->willReturn($tinyUrlMock);

        $this->httpUtilityMock->expects($this->once())
            ->method('redirect')
            ->with('http://the-target.url', 'HTTP/1.1 301 Moved Permanently');

        $this->eidController->main();
    }
}
