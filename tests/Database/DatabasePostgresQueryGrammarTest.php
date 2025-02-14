<?php

namespace Illuminate\Tests\Database;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Connection;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\PostgresGrammar;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class DatabasePostgresQueryGrammarTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function testToRawSql()
    {
        $connection = m::mock(Connection::class);
        $connection->shouldReceive('escape')->with('foo', false)->andReturn("'foo'");
        $grammar = new PostgresGrammar($connection);

        $query = $grammar->substituteBindingsIntoRawSql(
            'select * from "users" where \'{}\' ?? \'Hello\\\'\\\'World?\' AND "email" = ?',
            ['foo'],
        );

        $this->assertSame('select * from "users" where \'{}\' ? \'Hello\\\'\\\'World?\' AND "email" = \'foo\'', $query);
    }

    public function testCustomOperators()
    {
        PostgresGrammar::customOperators(['@@@', '@>', '']);
        PostgresGrammar::customOperators(['@@>', 1]);

        $connection = m::mock(Connection::class);
        $grammar = new PostgresGrammar($connection);

        $operators = $grammar->getOperators();

        $this->assertIsList($operators);
        $this->assertContains('@@@', $operators);
        $this->assertContains('@@>', $operators);
        $this->assertNotContains('', $operators);
        $this->assertNotContains(1, $operators);
        $this->assertSame(array_unique($operators), $operators);
    }

    public function testCompileTruncate()
    {
        $connection = m::mock(Connection::class);
        $connection->shouldReceive('getTablePrefix')->andReturn('');

        $postgres = new PostgresGrammar($connection);
        $builder = m::mock(Builder::class);
        $builder->from = 'users';

        $this->assertEquals([
            'truncate "users" restart identity cascade' => [],
        ], $postgres->compileTruncate($builder));

        PostgresGrammar::cascadeOnTrucate(false);

        $this->assertEquals([
            'truncate "users" restart identity' => [],
        ], $postgres->compileTruncate($builder));

        PostgresGrammar::cascadeOnTrucate();

        $this->assertEquals([
            'truncate "users" restart identity cascade' => [],
        ], $postgres->compileTruncate($builder));
    }

    public function testDateTimeInterfaceIsSerializedAsTimestamptz()
    {
        $connection = m::mock(PostgresConnection::class)->makePartial();
        $connection->useDefaultQueryGrammar();

        $bindings = $connection->prepareBindings([
            DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', '2016-04-29 23:01:57.820463', new DateTimeZone('Pacific/Tahiti')),
        ]);

        $this->assertEquals([
            '2016-04-29 23:01:57.820463-10:00',
        ], $bindings);
    }
}
