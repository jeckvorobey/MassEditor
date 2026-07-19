<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TestHelpers.php';

$controller_file = __DIR__ . '/../../wa-apps/shop/plugins/masseditor/lib/actions/shopMasseditorPluginBackendRollback.controller.php';
if (is_file($controller_file)) {
    require_once $controller_file;
}

class BackendRollbackControllerTest extends TestCase
{
    protected function setUp(): void
    {
        waRequest::reset();
        waLog::reset();
        $GLOBALS['fake_wa_system'] = new FakeWaSystem();
        $GLOBALS['fake_wa_system']->plugins['masseditor'] = new shopMasseditorPlugin(array(
            'operation_limit' => 100,
            'interface_language' => 'ru_RU',
        ));
    }

    public function testSuccessfulConfirmedPostReturnsReloadInstruction(): void
    {
        $this->assertTrue(class_exists('shopMasseditorPluginBackendRollbackController'));
        $controller = new StubbedBackendRollbackController();
        $controller->rollbackService = new SuccessfulBackendRollbackServiceStub();
        waRequest::$method = 'post';
        waRequest::$post = array('log_id' => '55', 'confirm_rollback' => '1');

        $controller->execute();

        $this->assertSame('55', $controller->rollbackService->rawLogId);
        $this->assertSame(1, $controller->rollbackService->confirmed);
        $this->assertSame('Старые значения восстановлены.', $controller->response['message']);
        $this->assertTrue($controller->response['reload']);
        $this->assertSame(array(), $controller->errors);
    }

    public function testControllerRejectsGetAndNonAdminBeforeRollback(): void
    {
        $this->assertTrue(class_exists('shopMasseditorPluginBackendRollbackController'));
        $controller = new StubbedBackendRollbackController();
        $controller->rollbackService = new SuccessfulBackendRollbackServiceStub();
        waRequest::$get = array('log_id' => '55', 'confirm_rollback' => '1');

        $controller->execute();

        $this->assertSame(array('Некорректный запрос отката.'), $controller->errors);
        $this->assertSame(0, $controller->rollbackService->rollbackCalls);

        waRequest::$method = 'post';
        waRequest::$post = array('log_id' => '55', 'confirm_rollback' => '1');
        $GLOBALS['fake_wa_system']->user = new FakeUser(9, false);
        $controller->execute();

        $this->assertSame(array('Недостаточно прав для управления массовым редактором.'), $controller->errors);
        $this->assertSame(0, $controller->rollbackService->rollbackCalls);
    }

    public function testStrictLogIdIsNotSilentlyConvertedByController(): void
    {
        $this->assertTrue(class_exists('shopMasseditorPluginBackendRollbackController'));
        $controller = new StubbedBackendRollbackController();
        $controller->rollbackService = new InvalidBackendRollbackServiceStub();
        waRequest::$method = 'post';
        waRequest::$post = array('log_id' => '55.9', 'confirm_rollback' => '1');

        $controller->execute();

        $this->assertSame('55.9', $controller->rollbackService->rawLogId);
        $this->assertSame(array('Некорректный запрос отката.'), $controller->errors);
    }

    public function testUnexpectedErrorIsLoggedAndHidden(): void
    {
        $this->assertTrue(class_exists('shopMasseditorPluginBackendRollbackController'));
        $controller = new StubbedBackendRollbackController();
        $controller->rollbackService = new UnexpectedBackendRollbackServiceStub();
        waRequest::$method = 'post';
        waRequest::$post = array('log_id' => '55', 'confirm_rollback' => '1');

        $controller->execute();

        $this->assertSame(array('Операцию не удалось выполнить. Повторите действие или проверьте журнал ошибок.'), $controller->errors);
        $this->assertStringNotContainsString('SQLSTATE', $controller->errors[0]);
        $this->assertCount(1, waLog::$logs);
        $this->assertStringContainsString('SQLSTATE', waLog::$logs[0]['message']);
        $this->assertSame('shop/plugins/masseditor.log', waLog::$logs[0]['file']);
    }
}

if (class_exists('shopMasseditorPluginBackendRollbackController')) {
    class StubbedBackendRollbackController extends shopMasseditorPluginBackendRollbackController
    {
        public $rollbackService;

        protected function createRollbackService($operation_limit, $language)
        {
            return $this->rollbackService;
        }
    }
}

class SuccessfulBackendRollbackServiceStub extends shopMasseditorPluginRollbackService
{
    public $rollbackCalls = 0;
    public $rawLogId;
    public $confirmed;

    public function __construct()
    {
    }

    public function rollback($raw_log_id, $confirmed, $now = null)
    {
        $this->rollbackCalls++;
        $this->rawLogId = $raw_log_id;
        $this->confirmed = $confirmed;

        return array('message' => 'Старые значения восстановлены.');
    }
}

class InvalidBackendRollbackServiceStub extends SuccessfulBackendRollbackServiceStub
{
    public function rollback($raw_log_id, $confirmed, $now = null)
    {
        $this->rawLogId = $raw_log_id;
        throw new InvalidArgumentException('Некорректный запрос отката.');
    }
}

class UnexpectedBackendRollbackServiceStub extends SuccessfulBackendRollbackServiceStub
{
    public function rollback($raw_log_id, $confirmed, $now = null)
    {
        throw new RuntimeException('SQLSTATE[42000]: internal rollback failure');
    }
}
