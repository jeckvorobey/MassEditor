<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TestHelpers.php';

class LogServiceTest extends TestCase
{
    use InvokesPrivateMethods;

    protected function setUp(): void
    {
        waContact::$names = array();
        $GLOBALS['fake_wa_system'] = new FakeWaSystem();
    }

    public function testLogRejectsEmptyActionType(): void
    {
        $service = new shopMasseditorproductPluginLogService(new FakeLogModel());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Action type is required.');

        $service->log('', 3);
    }

    public function testLogNormalizesUserAndDescription(): void
    {
        $model = new FakeLogModel();
        $service = new shopMasseditorproductPluginLogService($model);
        $GLOBALS['fake_wa_system']->user = new FakeUser(42, true);

        $service->log('price', -5, '  demo  ');

        $this->assertSame(42, $model->inserted[0]['user_id']);
        $this->assertSame(0, $model->inserted[0]['entity_count']);
        $this->assertSame('demo', $model->inserted[0]['description']);
    }

    public function testGetLatestAndPageUseBoundedPagination(): void
    {
        $model = new FakeLogModel();
        $model->queueResponse('SELECT id, user_id, action_type', new FakeQueryResult(array(
            array('id' => 3, 'action_type' => 'price'),
        )));
        $model->queueResponse('SELECT COUNT(*)', new FakeQueryResult(array(), 21));
        $model->queueResponse('SELECT id, user_id, action_type', new FakeQueryResult(array(
            array('id' => 3, 'action_type' => 'price'),
            array('id' => 2, 'action_type' => 'tags'),
        )));

        $service = new shopMasseditorproductPluginLogService($model);
        $this->assertCount(1, $service->getLatest(0));

        $page = $service->getPage(99, 20);
        $this->assertSame(2, $page['pagination']['page']);
        $this->assertSame(2, $page['pagination']['pages']);
        $this->assertCount(2, $page['logs']);
    }

    public function testPurgeOlderThanDaysShortCircuitsAndDeletes(): void
    {
        $model = new FakeLogModel();
        $service = new shopMasseditorproductPluginLogService($model);

        $this->assertSame(0, $service->purgeOlderThanDays(0));
        $this->assertSame(1, $service->purgeOlderThanDays(10));
        $this->assertCount(1, $model->execs);
        $this->assertStringContainsString('DELETE FROM shop_masseditorproduct_log', $model->execs[0]['sql']);
    }
}
