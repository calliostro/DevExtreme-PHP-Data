<?php

declare(strict_types=1);

namespace DevExtreme;

final class DataSourceLoader
{
    /**
     * @throws \Exception
     */
    public static function load(DbSet $dbSet, array $params): ?array
    {
        $dbSet->select(Utils::getItemValueOrDefault($params, 'select'))
            ->filter(Utils::getItemValueOrDefault($params, 'filter'));

        $totalSummary = $dbSet->getTotalSummary(
            Utils::getItemValueOrDefault($params, 'totalSummary'),
            Utils::getItemValueOrDefault($params, 'filter')
        );

        if ($dbSet->getLastError() !== null) {
            return null;
        }

        $totalCount = (isset($params['requireTotalCount']) && $params['requireTotalCount'] === true)
            ? $dbSet->getCount() : null;

        if ($dbSet->getLastError() !== null) {
            return null;
        }

        $dbSet->sort(Utils::getItemValueOrDefault($params, 'sort'));
        $groupCount = null;
        $skip = Utils::getItemValueOrDefault($params, 'skip');
        $take = Utils::getItemValueOrDefault($params, 'take');

        if (isset($params['group'])) {
            $groupExpression = $params['group'];
            $groupSummary = Utils::getItemValueOrDefault($params, 'groupSummary');
            $dbSet->group($groupExpression, $groupSummary, $skip, $take);

            if (isset($params['requireGroupCount']) && $params['requireGroupCount'] === true) {
                $groupCount = $dbSet->getGroupCount();
            }
        } else {
            $dbSet->skipTake($skip, $take);
        }

        $result = [];
        $result['data'] = $dbSet->asArray();

        if ($dbSet->getLastError() !== null) {
            return $result;
        }

        if (isset($totalCount)) {
            $result['totalCount'] = $totalCount;
        }

        if (isset($totalSummary)) {
            $result['summary'] = $totalSummary;
        }

        if (isset($groupCount)) {
            $result['groupCount'] = $groupCount;
        }

        return $result;
    }
}
