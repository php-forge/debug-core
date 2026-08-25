<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Db;

use PHPForge\Debug\Panel\Db\DbExplainRenderer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see DbExplainRenderer} covering empty, failed, and tabular EXPLAIN result markup.
 */
#[Group('panel')]
#[Group('db')]
final class DbExplainRendererTest extends TestCase
{
    public function testRenderEmptyPlan(): void
    {
        self::assertSame(
            <<<HTML
            <div class="yii-debug-explain">
            <h1 class="yii-debug-explain-title">
            EXPLAIN
            </h1><p class="yii-debug-explain-empty">
            EXPLAIN returned no rows.
            </p>
            </div>
            HTML,
            DbExplainRenderer::render('', []),
            'Empty plans must keep the shared title and empty-state contract.',
        );
    }

    public function testRenderError(): void
    {
        self::assertSame(
            <<<HTML
            <div class="yii-debug-explain">
            <h1 class="yii-debug-explain-title">
            EXPLAIN
            </h1><pre class="yii-debug-explain-query">
            <span class="yii-debug-sql-kw">SELECT</span> <span class="yii-debug-sql-num">1</span>
            </pre><p class="yii-debug-explain-empty">
            EXPLAIN failed: database &lt;unavailable&gt;
            </p>
            </div>
            HTML,
            DbExplainRenderer::renderError('SELECT 1', 'database <unavailable>'),
            'Failed plans must preserve the query and escape the driver error exactly.',
        );
    }

    public function testRenderKeyedResultRowsAreReindexedBeforeColumnDetection(): void
    {
        self::assertSame(
            <<<HTML
            <div class="yii-debug-explain">
            <h1 class="yii-debug-explain-title">
            EXPLAIN
            </h1><pre class="yii-debug-explain-query">
            <span class="yii-debug-sql-kw">SELECT</span> <span class="yii-debug-sql-num">1</span>
            </pre><div class="yii-debug-explain-scroll">
            <table class="yii-debug-table yii-debug-explain-table">
            <thead>
            <tr>
            <th scope="col">
            id
            </th><th scope="col">
            op
            </th>
            </tr>
            </thead><tbody>
            <tr>
            <td>
            1
            </td><td>
            SCAN t
            </td>
            </tr><tr>
            <td>
            2
            </td><td>
            USE INDEX
            </td>
            </tr>
            </tbody>
            </table>
            </div>
            </div>
            HTML,
            DbExplainRenderer::render(
                'SELECT 1',
                [
                    3 => ['id' => 1, 'op' => 'SCAN t'],
                    7 => ['id' => 2, 'op' => 'USE INDEX'],
                ],
            ),
            'Non-list result keys must not leak into column detection.',
        );
    }

    public function testRenderQueryAndResultTable(): void
    {
        self::assertSame(
            <<<HTML
            <div class="yii-debug-explain">
            <h1 class="yii-debug-explain-title">
            EXPLAIN
            </h1><pre class="yii-debug-explain-query">
            <span class="yii-debug-sql-kw">SELECT</span> * <span class="yii-debug-sql-kw">FROM</span> users
            </pre><div class="yii-debug-explain-scroll">
            <table class="yii-debug-table yii-debug-explain-table">
            <thead>
            <tr>
            <th scope="col">
            id
            </th><th scope="col">
            detail
            </th>
            </tr>
            </thead><tbody>
            <tr>
            <td>
            1
            </td><td>
            SCAN users
            </td>
            </tr><tr>
            <td>
            <em>NULL</em>
            </td><td>
            SEARCH users
            </td>
            </tr><tr>
            <td>
            2
            </td><td>
            [
                'operator' =&gt; '&lt;scan&gt;',
            ]
            </td>
            </tr>
            </tbody>
            </table>
            </div>
            </div>
            HTML,
            DbExplainRenderer::render(
                'SELECT * FROM users',
                [
                    ['id' => 1, 'detail' => 'SCAN users'],
                    ['id' => null, 'detail' => 'SEARCH users'],
                    ['id' => 2, 'detail' => ['operator' => '<scan>']],
                ],
            ),
            'A populated plan must render scalar, null, and escaped non-scalar cells exactly.',
        );
    }

    public function testRenderSingleRowPlanDerivesColumnsFromTheFirstRow(): void
    {
        self::assertSame(
            <<<HTML
            <div class="yii-debug-explain">
            <h1 class="yii-debug-explain-title">
            EXPLAIN
            </h1><pre class="yii-debug-explain-query">
            <span class="yii-debug-sql-kw">SELECT</span> <span class="yii-debug-sql-num">1</span>
            </pre><div class="yii-debug-explain-scroll">
            <table class="yii-debug-table yii-debug-explain-table">
            <thead>
            <tr>
            <th scope="col">
            id
            </th><th scope="col">
            op
            </th>
            </tr>
            </thead><tbody>
            <tr>
            <td>
            1
            </td><td>
            SCAN t
            </td>
            </tr>
            </tbody>
            </table>
            </div>
            </div>
            HTML,
            DbExplainRenderer::render('SELECT 1', [['id' => 1, 'op' => 'SCAN t']]),
            'Header cells must come from the sole result row.',
        );
    }
}
