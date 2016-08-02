<?php
use queryBuilder\DB;
use queryBuilder\Builder;
use queryBuilder\Grammar;

class DatabaseQueryBuilderTest {

	public function __construct() {
		$methods = get_class_methods($this);
	
		foreach ($methods as $method) {
			if ($method != '__construct') {
				$reflection = new ReflectionMethod($this, $method);
				if ($reflection->isPublic()) {
					$this->{$method}();
				}
			}
		}
	}
	
    public function testBasicSelect() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users');
        $this->assertEquals('select * from `users`', $builder->toSql());
    }

    public function testBasicTableWrappingProtectsQuotationMarks() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('some`table');
		$this->assertEquals('select * from `some`table`', $builder->toSql());
    }

    public function testAliasWrappingAsWholeConstant() {
        $builder = $this->getBuilder();
        $builder->select('x.y as foo.bar')->from('baz');
        $this->assertEquals('select `x`.`y` as `foo.bar` from `baz`', $builder->toSql()); 
    }

    public function testBasicSelectDistinct() {
        $builder = $this->getBuilder();
        $builder->distinct()->select(['foo', 'bar'])->from('users');
        $this->assertEquals('select distinct `foo`, `bar` from `users`', $builder->toSql());
    }

    public function testBasicAlias() {
        $builder = $this->getBuilder();
        $builder->select('foo as bar')->from('users');
        $this->assertEquals('select `foo` as `bar` from `users`', $builder->toSql());
    }

    public function testBasicTableWrapping() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('public.users');
        $this->assertEquals('select * from `public`.`users`', $builder->toSql());
    }

    public function testBasicWheres() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1);
        $this->assertEquals('select * from `users` where `id` = ?', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    /*
    public function testWhereDayMySql()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereDay('created_at', '=', 1);
        $this->assertEquals('select * from `users` where day(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testWhereMonthMySql()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereMonth('created_at', '=', 5);
        $this->assertEquals('select * from `users` where month(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => 5], $builder->getBindings());
    }

    public function testWhereYearMySql()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereYear('created_at', '=', 2014);
        $this->assertEquals('select * from `users` where year(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => 2014], $builder->getBindings());
    }
*/

    public function testWhereBetweens() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereBetween('id', [1, 2]);
        $this->assertEquals('select * from `users` where `id` between ? and ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereBetween('id', [1, 2], 'and', true);
        $this->assertEquals('select * from `users` where `id` not between ? and ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
    }

    public function testBasicOrWheres() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->where('email', '=', 'foo', 'or');
        $this->assertEquals('select * from `users` where `id` = ? or `email` = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());
    }

    public function testBasicWhereIns() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIn('id', [1, 2, 3]);
        $this->assertEquals('select * from `users` where `id` in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2, 2 => 3], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->whereIn('id', [1, 2, 3], 'or');
        $this->assertEquals('select * from `users` where `id` = ? or `id` in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 1, 2 => 2, 3 => 3], $builder->getBindings());
    }

    public function testBasicWhereNotIns() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIn('id', [1, 2, 3], 'and', true);
        $this->assertEquals('select * from `users` where `id` not in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2, 2 => 3], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->whereIn('id', [1, 2, 3], 'or', true);
        $this->assertEquals('select * from `users` where `id` = ? or `id` not in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 1, 2 => 2, 3 => 3], $builder->getBindings());
    }

    public function testEmptyWhereIns() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIn('id', []);
        $this->assertEquals('select * from `users` where 0 = 1', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->whereIn('id', [], 'or');
        $this->assertEquals('select * from `users` where `id` = ? or 0 = 1', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testEmptyWhereNotIns() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIn('id', [], 'and', true);
        $this->assertEquals('select * from `users` where 1 = 1', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->whereIn('id', [], 'or', true);
        $this->assertEquals('select * from `users` where `id` = ? or 1 = 1', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testBasicWhereNulls() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNull('id');
        $this->assertEquals('select * from `users` where `id` is null', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->whereNull('id', 'or');
        $this->assertEquals('select * from `users` where `id` = ? or `id` is null', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testBasicWhereNotNulls() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNull('id', 'and', true);
        $this->assertEquals('select * from `users` where `id` is not null', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '>', 1)->whereNull('id', 'or', true);
        $this->assertEquals('select * from `users` where `id` > ? or `id` is not null', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testWhereWithArrayConditions() {
    	$builder = $this->getBuilder();
    	$builder->select('*')->from('users')->where(['foo' => 1, 'bar' => 2]);
    	$this->assertEquals('select * from `users` where `foo` = ? and `bar` = ?', $builder->toSql());
    	$this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
    }
    
    public function testGroupBys() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->groupBy('id', 'email');
        $this->assertEquals('select * from `users` group by `id`, `email`', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->groupBy(['id', 'email']);
        $this->assertEquals('select * from `users` group by `id`, `email`', $builder->toSql());
    }

    public function testOrderBys() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->orderBy('email')->orderBy('age', 'desc');
        $this->assertEquals('select * from `users` order by `email` asc, `age` desc', $builder->toSql());
    }

    public function testLimitsAndOffsets() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->offset(5)->limit(10);
        $this->assertEquals('select * from `users` limit 10 offset 5', $builder->toSql());
    }

    
    
    
    public function testBasicJoins() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', 'users.id', '=', 'contacts.id')->leftJoin('photos', 'users.id', '=', 'photos.id');
        $this->assertEquals('select * from `users` inner join `contacts` on `users`.`id` = `contacts`.`id` left join `photos` on `users`.`id` = `photos`.`id`', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->leftJoinWhere('photos', 'users.id', '=', 'bar')->joinWhere('photos', 'users.id', '=', 'foo');
        $this->assertEquals('select * from `users` left join `photos` on `users`.`id` = ? inner join `photos` on `users`.`id` = ?', $builder->toSql());
        $this->assertEquals(['bar', 'foo'], $builder->getBindings());
    }

    public function testCrossJoins() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('tableB')->join('tableA', 'tableA.column1', '=', 'tableB.column2', 'cross');
        $this->assertEquals('select * from `tableB` cross join `tableA` on `tableA`.`column1` = `tableB`.`column2`', $builder->toSql());
    }

    public function testComplexJoin()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->on('users.name', '=', 'contacts.name', 'or');
        });
        $this->assertEquals('select * from `users` inner join `contacts` on `users`.`id` = `contacts`.`id` or `users`.`name` = `contacts`.`name`', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->where('users.id', '=', 'foo')->where('users.name', '=', 'bar', 'or');
        });
        $this->assertEquals('select * from `users` inner join `contacts` on `users`.`id` = ? or `users`.`name` = ?', $builder->toSql());
        $this->assertEquals(['foo', 'bar'], $builder->getBindings());

        // Run the assertions again
        $this->assertEquals('select * from `users` inner join `contacts` on `users`.`id` = ? or `users`.`name` = ?', $builder->toSql());
        $this->assertEquals(['foo', 'bar'], $builder->getBindings());
    }

    public function testJoinWhereNull() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->whereNull('contacts.deleted_at');
        });
        $this->assertEquals('select * from `users` inner join `contacts` on `users`.`id` = `contacts`.`id` and `contacts`.`deleted_at` is null', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->whereNull('contacts.deleted_at', 'or');
        });
        $this->assertEquals('select * from `users` inner join `contacts` on `users`.`id` = `contacts`.`id` or `contacts`.`deleted_at` is null', $builder->toSql());
    }

    public function testJoinWhereNotNull() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->whereNull('contacts.deleted_at', 'and', true);
        });
        $this->assertEquals('select * from `users` inner join `contacts` on `users`.`id` = `contacts`.`id` and `contacts`.`deleted_at` is not null', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->whereNull('contacts.deleted_at', 'or', true);
        });
        $this->assertEquals('select * from `users` inner join `contacts` on `users`.`id` = `contacts`.`id` or `contacts`.`deleted_at` is not null', $builder->toSql());
    }

    public function testJoinWhereIn() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->whereIn('contacts.name', [48, 'baz', null]);
        });
      
        $this->assertEquals('select * from `users` inner join `contacts` on `users`.`id` = `contacts`.`id` and `contacts`.`name` in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([48, 'baz', null], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->whereIn('contacts.name', [48, 'baz', null], 'or');
        });
        $this->assertEquals('select * from `users` inner join `contacts` on `users`.`id` = `contacts`.`id` or `contacts`.`name` in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([48, 'baz', null], $builder->getBindings());
    }

    public function testJoinWhereNotIn() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->whereIn('contacts.name', [48, 'baz', null], 'and', true);
        });
        $this->assertEquals('select * from `users` inner join `contacts` on `users`.`id` = `contacts`.`id` and `contacts`.`name` not in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([48, 'baz', null], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->whereIn('contacts.name', [48, 'baz', null], 'or', true);
        });
        $this->assertEquals('select * from `users` inner join `contacts` on `users`.`id` = `contacts`.`id` or `contacts`.`name` not in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([48, 'baz', null], $builder->getBindings());
    }

    public function testInsertMethod() {
        $builder = $this->getBuilder();
        $builder->from('users')->insert(['email' => 'foo']);
        $this->assertEquals('insert into `users` (`email`) values (?)', $builder->toSql());
        $this->assertEquals(['foo'], $builder->getBindings());
    }

    public function testUpdateMethod() {
        $builder = $this->getBuilder();
        $builder->from('users')->update(['email' => 'foo', 'name' => 'bar'])->where('id', '=', 1);
        $this->assertEquals('update `users` set `email` = ?, `name` = ? where `id` = ?', $builder->toSql());
        $bindings = array_values(array_merge($builder->updates, $builder->getBindings()));
        $this->assertEquals(['foo', 'bar', 1], $bindings);
        
        $builder = $this->getBuilder();
        $builder->from('users')->where('id', '=', 1)->orderBy('foo', 'desc')->limit(5)->update(['email' => 'foo', 'name' => 'bar']);
        $this->assertEquals('update `users` set `email` = ?, `name` = ? where `id` = ? order by `foo` desc limit 5', $builder->toSql());
        $bindings = array_values(array_merge($builder->updates, $builder->getBindings()));
        $this->assertEquals(['foo', 'bar', 1], $bindings);
    }

    public function testUpdateMethodWithJoins() {
    	$builder = $this->getBuilder();
    	$builder->from('users')->join('orders', 'users.id', '=', 'orders.user_id')->where('users.id', '=', 1)->update(['email' => 'foo', 'name' => 'bar']);
    	$this->assertEquals('update `users` inner join `orders` on `users`.`id` = `orders`.`user_id` set `email` = ?, `name` = ? where `users`.`id` = ?', $builder->toSql());
    	$bindings = array_values(array_merge($builder->updates, $builder->getBindings()));
    	$this->assertEquals(['foo', 'bar', 1], $bindings);
    }

    public function testDeleteMethod() {
        $builder = $this->getBuilder();
    	$builder->from('users')->where('email', '=', 'foo')->delete();
    	$this->assertEquals('delete from `users` where `email` = ?', $builder->toSql());
    	$this->assertEquals(['foo'], $builder->getBindings());
    	
    	$builder = $this->getBuilder();
    	$builder->from('users')->delete(1);
    	$this->assertEquals('delete from `users` where `id` = ?', $builder->toSql());
    	$this->assertEquals([1], $builder->getBindings());
    }

    public function testDeleteWithJoinMethod() {
    	$builder = $this->getBuilder();
    	$builder->from('users')->join('contacts', 'users.id', '=', 'contacts.id')->where('email', '=', 'foo')->orderBy('id')->limit(1)->delete();
    	$this->assertEquals('delete `users` from `users` inner join `contacts` on `users`.`id` = `contacts`.`id` where `email` = ?', $builder->toSql());
    	$this->assertEquals(['foo'], $builder->getBindings());
    	
    	$builder = $this->getBuilder();
    	$builder->from('users')->join('contacts', 'users.id', '=', 'contacts.id')->orderBy('id')->delete(1);
    	$this->assertEquals('delete `users` from `users` inner join `contacts` on `users`.`id` = `contacts`.`id` where `id` = ?', $builder->toSql());
    	$this->assertEquals([1], $builder->getBindings());
    }

    public function testAddBindingWithArrayMergesBindings() {
        $builder = $this->getBuilder();
        $builder->addBinding(['foo', 'bar'], 'where');
        $builder->addBinding(['baz'], 'where');
        $this->assertEquals(['foo', 'bar', 'baz'], $builder->getBindings());
    }

    public function testAddBindingWithArrayMergesBindingsInCorrectOrder() {
        $builder = $this->getBuilder();
        $builder->addBinding(['bar', 'baz'], 'having');
        $builder->addBinding(['foo'], 'where');
        $this->assertEquals(['foo', 'bar', 'baz'], $builder->getBindings());
    }
   
    /* Tools */
    
    protected function getBuilder() {
        return $query = new Builder(new DB([]), new Grammar());
    }
    
    private function output($method, $result) {
    	echo $method . ': ';
    	echo $result ? '<span style="color: green">Success</span>': '<span style="color: red">Failer!</span>';
    	echo '<br />';
    }
    
    private function assertEquals($expected, $actual) {
    	$result = $expected === $actual;
    	$method = debug_backtrace()[1]['function'];
    	
    	return $this->output($method, $result);
    }
    
    private function assertNull($actual) {
    	$result = !isset($actual);
    	$method = debug_backtrace()[1]['function'];
    	
    	return $this->output($method, $result);
    }
    
}