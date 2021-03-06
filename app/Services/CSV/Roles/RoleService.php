<?php
declare(strict_types=1);
/**
 * RoleService.php
 * Copyright (c) 2020 james@firefly-iii.org
 *
 * This file is part of the Firefly III CSV importer
 * (https://github.com/firefly-iii/csv-importer).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace App\Services\CSV\Roles;

use App\Services\CSV\Configuration\Configuration;
use App\Services\CSV\Specifics\SpecificInterface;
use App\Services\CSV\Specifics\SpecificService;
use League\Csv\Exception;
use League\Csv\Reader;
use League\Csv\Statement;
use Log;
use RuntimeException;

/**
 * Class RoleService
 */
class RoleService
{
    public const EXAMPLE_LENGTH = 26;
    public const EXAMPLE_COUNT  = 7;

    /**
     * @param string        $content
     * @param Configuration $configuration
     *
     * @throws Exception
     * @return array
     */
    public static function getColumns(string $content, Configuration $configuration): array
    {
        $reader  = Reader::createFromString($content);

        // configure reader:
        $delimiter = $configuration->getDelimiter();
        switch($delimiter) {
            case 'semicolon':
                $reader->setDelimiter(';');
                break;
            case 'comma':
                $reader->setDelimiter(',');
                break;
            case 'tab':
                $reader->setDelimiter("\t");
                break;
        }

        $headers = [];
        if (true === $configuration->isHeaders()) {
            try {
                $stmt    = (new Statement)->limit(1)->offset(0);
                $records = $stmt->process($reader);
                $headers = $records->fetchOne();
                // @codeCoverageIgnoreStart
            } catch (Exception $e) {
                Log::error($e->getMessage());
                throw new RuntimeException($e->getMessage());
            }
            // @codeCoverageIgnoreEnd
            Log::debug('Detected file headers:', $headers);
        }
        if (false === $configuration->isHeaders()) {
            try {
                $stmt    = (new Statement)->limit(1)->offset(0);
                $records = $stmt->process($reader);
                $count   = count($records->fetchOne());
                for ($i = 0; $i < $count; $i++) {
                    $headers[] = sprintf('Column #%d', $i + 1);
                }

                // @codeCoverageIgnoreStart
            } catch (Exception $e) {
                Log::error($e->getMessage());
                throw new RuntimeException($e->getMessage());
            }
        }

        // specific processors may add or remove headers.
        // so those must be processed as well.
        /** @var string $name */
        foreach ($configuration->getSpecifics() as $name => $enabled) {
            if ($enabled && SpecificService::exists($name)) {
                /** @var SpecificInterface $object */
                $object  = app(SpecificService::fullClass($name));
                $headers = $object->runOnHeaders($headers);
            }
        }

        return $headers;
    }

    /**
     * @param string        $content
     * @param Configuration $configuration
     *
     * @throws Exception
     * @return array
     */
    public static function getExampleData(string $content, Configuration $configuration): array
    {
        $reader   = Reader::createFromString($content);

        // configure reader:
        $delimiter = $configuration->getDelimiter();
        switch($delimiter) {
            case 'semicolon':
                $reader->setDelimiter(';');
                break;
            case 'comma':
                $reader->setDelimiter(',');
                break;
            case 'tab':
                $reader->setDelimiter("\t");
                break;
        }

        $offset   = $configuration->isHeaders() ? 1 : 0;
        $examples = [];
        // make statement.
        try {
            $stmt = (new Statement)->limit(self::EXAMPLE_COUNT)->offset($offset);
            // @codeCoverageIgnoreStart
        } catch (Exception $e) {
            Log::error($e->getMessage());
            throw new RuntimeException($e->getMessage());
        }
        // @codeCoverageIgnoreEnd

        // grab the records:
        $records = $stmt->process($reader);
        /** @var array $line */
        foreach ($records as $line) {
            $line = array_values($line);
            $line = SpecificService::runSpecifics($line, $configuration->getSpecifics());
            foreach ($line as $index => $cell) {
                if (strlen($cell) > self::EXAMPLE_LENGTH) {
                    $cell = sprintf('%s...', substr($cell, 0, self::EXAMPLE_LENGTH));
                }
                $examples[$index][] = $cell;
                $examples[$index]   = array_unique($examples[$index]);
            }
        }

        return $examples;
    }

}
