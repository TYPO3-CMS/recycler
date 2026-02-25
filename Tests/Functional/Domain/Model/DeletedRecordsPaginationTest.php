<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace TYPO3\CMS\Recycler\Tests\Functional\Domain\Model;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Recycler\Domain\Model\DeletedRecords;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class DeletedRecordsPaginationTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['recycler'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/Database/be_groups.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/Database/be_users.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/Database/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/Database/tt_content.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    #[Test]
    public function makeInstanceReturnsFreshInstancesForDeletedRecords(): void
    {
        $first = GeneralUtility::makeInstance(DeletedRecords::class);
        $second = GeneralUtility::makeInstance(DeletedRecords::class);
        self::assertNotSame($first, $second, 'Each makeInstance call must return a separate instance');
    }

    #[Test]
    public function loadDataWithoutLimitLoadsAllRecordsConsistently(): void
    {
        $model1 = GeneralUtility::makeInstance(DeletedRecords::class);
        $model1->loadData(1, '', 999);
        $firstCount = $this->countFlatRecords($model1->getDeletedRows());
        self::assertGreaterThan(0, $firstCount, 'Fixtures should contain deleted records');

        $model2 = GeneralUtility::makeInstance(DeletedRecords::class);
        $model2->loadData(1, '', 999);
        $secondCount = $this->countFlatRecords($model2->getDeletedRows());

        self::assertSame(
            $firstCount,
            $secondCount,
            'Repeated loadData calls on fresh instances must return the same number of records'
        );
    }

    #[Test]
    public function arrayPaginatorPaginationCoversAllRecordsWithoutDuplicates(): void
    {
        $pageSize = 3;

        $model = GeneralUtility::makeInstance(DeletedRecords::class);
        $model->loadData(1, '', 999);

        $flatRecords = [];
        foreach ($model->getDeletedRows() as $tableName => $rows) {
            foreach ($rows as $row) {
                $flatRecords[] = ['_table' => $tableName, ...$row];
            }
        }
        $expectedTotal = count($flatRecords);
        self::assertGreaterThan($pageSize, $expectedTotal, 'Need more records than page size to test pagination');

        $allRecords = [];
        $totalPages = (int)ceil($expectedTotal / $pageSize);
        for ($page = 1; $page <= $totalPages; $page++) {
            $paginator = new ArrayPaginator($flatRecords, $page, $pageSize);
            $pageItems = $paginator->getPaginatedItems();

            if ($page < $totalPages) {
                self::assertCount(
                    $pageSize,
                    $pageItems,
                    'Page ' . $page . ' should have exactly ' . $pageSize . ' records'
                );
            } else {
                $expectedRemainder = $expectedTotal - ($totalPages - 1) * $pageSize;
                self::assertCount(
                    $expectedRemainder,
                    $pageItems,
                    'Last page should have ' . $expectedRemainder . ' records'
                );
            }

            foreach ($pageItems as $item) {
                $allRecords[] = $item['_table'] . ':' . $item['uid'];
            }
        }

        self::assertCount(
            count($allRecords),
            array_unique($allRecords),
            'No duplicate records should appear across pages'
        );

        self::assertCount(
            $expectedTotal,
            $allRecords,
            'Pagination must cover all ' . $expectedTotal . ' deleted records'
        );
    }

    private function countFlatRecords(array $deletedRows): int
    {
        $count = 0;
        foreach ($deletedRows as $rows) {
            $count += count($rows);
        }
        return $count;
    }
}
