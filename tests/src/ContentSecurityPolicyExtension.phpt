<?php

namespace Adeira\Tests;

use Adeira\ContentSecurityPolicyExtension as Csp;
use Nette\DI\Compiler;
use Nette\PhpGenerator\ClassType;
use Nette\Utils\Validators;
use Tester\Assert;
use Tester\FileMock;

require dirname(__DIR__) . '/bootstrap.php';

/**
 * @testCase
 */
class ContentSecurityPolicyExtension extends \Tester\TestCase
{

	/** @var \Nette\PhpGenerator\ClassType */
	private $container;

	public function setUp()
	{
		$container = new ClassType('Container');
		$container->addMethod('initialize');
		$this->container = $container;
	}

	public function testDisabled()
	{
		$this->prepare([
			'enabled' => FALSE,
		]);
		Assert::same('', $this->container->getMethod('initialize')->getBody());
	}

	public function testEnabledDefault()
	{
		$this->prepare();
		Assert::same(
			"header(\"Content-Security-Policy: default-src 'self'; script-src * 'unsafe-inline' 'unsafe-eval'; style-src * 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; font-src *; object-src *; media-src *; child-src *; form-action 'self'; frame-ancestors 'self';\");\n",
			$this->container->getMethod('initialize')->getBody()
		);
	}

	public function testReportOnly()
	{
		Assert::exception(function () {
			$this->prepare([
				'report-only' => TRUE,
			]);
		}, 'LogicException', "You have to setup 'report-uri' if you want to use 'report-only' mode.");
		$this->prepare([
			'report-only' => TRUE,
			'report-uri' => 'relative/path',
		]);
		Assert::same(
			"\$_csp_report_url = \$this->getByType('Nette\\Http\\Request')->getUrl()->getBaseUrl();\n"
			. "header(\"Content-Security-Policy-Report-Only: default-src 'self'; script-src * 'unsafe-inline' 'unsafe-eval'; style-src * 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; font-src *; object-src *; media-src *; child-src *; form-action 'self'; frame-ancestors 'self'; report-uri {\$_csp_report_url}relative/path;\");\n",
			$this->container->getMethod('initialize')->getBody()
		);
	}

	public function testConfigValues()
	{
		$this->prepare([
			'default-src' => "'none'",
			'script-src' => "'none'",
			'style-src' => "'none'",
			'img-src' => "'none'",
			'connect-src' => "'none'",
			'font-src' => "'none'",
			'object-src' => "'none'",
			'media-src' => "'none'",
			'child-src' => "'none'",
			'form-action' => "'none'",
			'frame-ancestors' => "'none'",
		]);
		Assert::same(
			"header(\"Content-Security-Policy: default-src 'none'; script-src 'none'; style-src 'none'; img-src 'none'; connect-src 'none'; font-src 'none'; object-src 'none'; media-src 'none'; child-src 'none'; form-action 'none'; frame-ancestors 'none';\");\n",
			$this->container->getMethod('initialize')->getBody()
		);
	}

	public function testApostrophes()
	{
		$this->prepare([
			'a' => 'none',
			'b' => '* self',
			'c' => 'test',
			'd' => 'unsafe-eval *.ur.l',
			'e' => 'unsafe-inline',
			'f' => "'none'",
		]);
		Assert::same(
			"header(\"Content-Security-Policy: default-src 'self'; script-src * 'unsafe-inline' 'unsafe-eval'; style-src * 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; font-src *; object-src *; media-src *; child-src *; form-action 'self'; frame-ancestors 'self';"
			. " a 'none'; b * 'self'; c test; d 'unsafe-eval' *.ur.l; e 'unsafe-inline'; f 'none';\");\n",
			$this->container->getMethod('initialize')->getBody()
		);
	}

	public function testArrayValues()
	{
		$config = <<<NEON
csp:
	default-src:
		- self
		customKey: https://*.url.l
	script-src:
		- *
		- unsafe-inline
		- unsafe-eval
	style-src: * unsafe-inline
NEON;
		$this->prepare(FileMock::create($config, 'neon'));
		Assert::same(
			"header(\"Content-Security-Policy: default-src 'self' https://*.url.l; script-src * 'unsafe-inline' 'unsafe-eval'; style-src * 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; font-src *; object-src *; media-src *; child-src *; form-action 'self'; frame-ancestors 'self';\");\n",
			$this->container->getMethod('initialize')->getBody()
		);
	}

	private function prepare($config = [])
	{
		Validators::assert($config, 'array|string');
		$csp = (new Csp)->setCompiler(new Compiler, 'csp');
		if (is_string($config) && is_file($config)) {
			$csp->setConfig($csp->loadFromFile($config)['csp']);
		} else {
			$csp->setConfig($config);
		}
		$csp->afterCompile($this->container);
	}

}

(new ContentSecurityPolicyExtension)->run();
