<?php
namespace unit;

// @codingStandardsIgnoreFile
// We do not want NitPick CI to report results about this file,
// as we have a couple of private test classes that appear in this file
// rather than in their own file.

use Robo\Result;
use Robo\Task\BaseTask;
use Robo\Contract\TaskInterface;
use Robo\Collection\Collection;
use Robo\Config;

class CollectionTest extends \Codeception\TestCase\Test
{
    protected $container;

    protected function _before()
    {
        $this->container = Config::getContainer();
        $this->container->addServiceProvider(\Robo\Collection\Collection::getCollectionServices());
    }

    public function testBeforeAndAfterFilters()
    {
        $collection = $this->container->get('collection');

        $taskA = new CollectionTestTask('a', 'value-a');
        $taskB = new CollectionTestTask('b', 'value-b');

        $collection
            ->add($taskA, 'a-name')
            ->add($taskB, 'b-name');

        // We add methods of our task instances as before and
        // after tasks. These methods have access to the task
        // class' fields, and may modify them as needed.
        $collection
            ->afterCode('a-name', [$taskA, 'parenthesizer'])
            ->afterCode('a-name', [$taskA, 'emphasizer'])
            ->afterCode('b-name', [$taskB, 'emphasizer'])
            ->afterCode('b-name', [$taskB, 'parenthesizer'])
            ->afterCode('b-name', [$taskB, 'parenthesizer'], 'special-name');

        $result = $collection->run();

        // verify(var_export($result->getData(), true))->equals('');

        // Ensure that the results have the correct key values
        verify(implode(',', array_keys($result->getData())))->equals('a-name,b-name,special-name');

        // Verify that all of the after tasks ran in
        // the correct order.
        verify($result['a-name']['a'])->equals('*(value-a)*');
        verify($result['b-name']['b'])->equals('(*value-b*)');

        // Note that the last after task is added with a special name;
        // its results therefore show up under the name given, rather
        // than being stored under the name of the task it was added after.
        verify($result['special-name']['b'])->equals('((*value-b*))');
    }
}

class CollectionTestTask extends BaseTask
{
    protected $key;
    protected $value;

    public function __construct($key, $value)
    {
        $this->key = $key;
        $this->value = $value;
    }

    public function run()
    {
        return $this->getValue();
    }

    protected function getValue()
    {
        $result = Result::success($this);
        $result[$this->key] = $this->value;

        return $result;
    }

    // Note that by returning a value with the same
    // key as the result, we overwrite the value generated
    // by the primary task method ('run()').  If we returned
    // a result with a different key, then both values
    // would appear in the result.
    public function parenthesizer()
    {
        $this->value = "({$this->value})";
        return $this->getValue();
    }

    public function emphasizer()
    {
        $this->value = "*{$this->value}*";
        return $this->getValue();
    }
}
