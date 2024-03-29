<?php

namespace Railken\Artisan;

use Dotenv\Dotenv;
use Orchestra\Testbench\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

class ApplicationFinder
{
    public function findTestFiles($path)
    {
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

        $files = [];

        foreach ($rii as $file) {
            if ($file->isDir()) {
                continue;
            }

            $files[] = $file->getPathname();
        }

        return $files;
    }

    public function loadEnvironment()
    {
        try {
            if (method_exists(Dotenv::class, 'create')) {
                $dotenv = Dotenv::create(getcwd());
            } else {
                $dotenv = new Dotenv(getcwd());
            }

            $dotenv->load();
        } catch (\Dotenv\Exception\InvalidPathException $e) {
            // Ignore missing .env files.
        }
    }

    public function findApplication()
    {
        $this->loadEnvironment();

        $classes = [];

        foreach ($this->findTestFiles(getcwd().'/tests') as $file) {
            if (is_file($file)) {
                $class = $this->getClassNamespaceFromFile($file).'\\'.$this->getClassNameFromFile($file);

                if (is_subclass_of($class, TestCase::class)) {
                    $reflection = new ReflectionClass($class);

                    if (!$reflection->isAbstract()) {
                        $classes[] = $class;
                        break;
                    }
                }
            }
        }

        $test = new $classes[0]();
        $test->setUp();

        $reflection = new ReflectionClass($test);

        $property = $reflection->getProperty('app');
        $property->setAccessible(true);
        $app = $property->getValue($test);

        return $app;
    }

    /**
     * get the class namespace form file path using token.
     *
     * @param $filePathName
     *
     * @return string|null
     */
    protected function getClassNamespaceFromFile($filePathName)
    {
        $src = file_get_contents($filePathName);

        $tokens = token_get_all($src);
        $count = count($tokens);
        $i = 0;
        $namespace = '';
        $namespace_ok = false;
        while ($i < $count) {
            $token = $tokens[$i];
            if (is_array($token) && $token[0] === T_NAMESPACE) {
                // Found namespace declaration
                while (++$i < $count) {
                    if ($tokens[$i] === ';') {
                        $namespace_ok = true;
                        $namespace = trim($namespace);
                        break;
                    }
                    $namespace .= is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
                }
                break;
            }
            ++$i;
        }
        if (!$namespace_ok) {
            return null;
        } else {
            return $namespace;
        }
    }

    /**
     * get the class name form file path using token.
     *
     * @param $filePathName
     *
     * @return mixed
     */
    protected function getClassNameFromFile($filePathName)
    {
        $php_code = file_get_contents($filePathName);

        $classes = [];
        $tokens = token_get_all($php_code);
        $count = count($tokens);
        for ($i = 2; $i < $count; ++$i) {
            if ($tokens[$i - 2][0] == T_CLASS
                && $tokens[$i - 1][0] == T_WHITESPACE
                && $tokens[$i][0] == T_STRING
            ) {
                $class_name = $tokens[$i][1];
                $classes[] = $class_name;
            }
        }

        return count($classes) > 0 ? $classes[0] : null;
    }
}
