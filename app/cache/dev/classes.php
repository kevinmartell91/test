<?php 
namespace Symfony\Component\EventDispatcher
{
interface EventSubscriberInterface
{
public static function getSubscribedEvents();
}
}
namespace Symfony\Component\HttpKernel\EventListener
{
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
abstract class SessionListener implements EventSubscriberInterface
{
public function onKernelRequest(GetResponseEvent $event)
{
if (!$event->isMasterRequest()) {
return;
}
$request = $event->getRequest();
$session = $this->getSession();
if (null === $session || $request->hasSession()) {
return;
}
$request->setSession($session);
}
public static function getSubscribedEvents()
{
return array(
KernelEvents::REQUEST => array('onKernelRequest', 128),
);
}
abstract protected function getSession();
}
}
namespace Symfony\Bundle\FrameworkBundle\EventListener
{
use Symfony\Component\HttpKernel\EventListener\SessionListener as BaseSessionListener;
use Symfony\Component\DependencyInjection\ContainerInterface;
class SessionListener extends BaseSessionListener
{
private $container;
public function __construct(ContainerInterface $container)
{
$this->container = $container;
}
protected function getSession()
{
if (!$this->container->has('session')) {
return;
}
return $this->container->get('session');
}
}
}
namespace Symfony\Component\HttpFoundation\Session\Storage
{
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
interface SessionStorageInterface
{
public function start();
public function isStarted();
public function getId();
public function setId($id);
public function getName();
public function setName($name);
public function regenerate($destroy = false, $lifetime = null);
public function save();
public function clear();
public function getBag($name);
public function registerBag(SessionBagInterface $bag);
public function getMetadataBag();
}
}
namespace Symfony\Component\HttpFoundation\Session\Storage
{
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Proxy\NativeProxy;
use Symfony\Component\HttpFoundation\Session\Storage\Proxy\AbstractProxy;
use Symfony\Component\HttpFoundation\Session\Storage\Proxy\SessionHandlerProxy;
class NativeSessionStorage implements SessionStorageInterface
{
protected $bags;
protected $started = false;
protected $closed = false;
protected $saveHandler;
protected $metadataBag;
public function __construct(array $options = array(), $handler = null, MetadataBag $metaBag = null)
{
session_cache_limiter(''); ini_set('session.use_cookies', 1);
if (PHP_VERSION_ID >= 50400) {
session_register_shutdown();
} else {
register_shutdown_function('session_write_close');
}
$this->setMetadataBag($metaBag);
$this->setOptions($options);
$this->setSaveHandler($handler);
}
public function getSaveHandler()
{
return $this->saveHandler;
}
public function start()
{
if ($this->started) {
return true;
}
if (PHP_VERSION_ID >= 50400 && \PHP_SESSION_ACTIVE === session_status()) {
throw new \RuntimeException('Failed to start the session: already started by PHP.');
}
if (PHP_VERSION_ID < 50400 && !$this->closed && isset($_SESSION) && session_id()) {
throw new \RuntimeException('Failed to start the session: already started by PHP ($_SESSION is set).');
}
if (ini_get('session.use_cookies') && headers_sent($file, $line)) {
throw new \RuntimeException(sprintf('Failed to start the session because headers have already been sent by "%s" at line %d.', $file, $line));
}
if (!session_start()) {
throw new \RuntimeException('Failed to start the session');
}
$this->loadSession();
if (!$this->saveHandler->isWrapper() && !$this->saveHandler->isSessionHandlerInterface()) {
$this->saveHandler->setActive(true);
}
return true;
}
public function getId()
{
return $this->saveHandler->getId();
}
public function setId($id)
{
$this->saveHandler->setId($id);
}
public function getName()
{
return $this->saveHandler->getName();
}
public function setName($name)
{
$this->saveHandler->setName($name);
}
public function regenerate($destroy = false, $lifetime = null)
{
if (PHP_VERSION_ID >= 50400 && \PHP_SESSION_ACTIVE !== session_status()) {
return false;
}
if (PHP_VERSION_ID < 50400 &&''=== session_id()) {
return false;
}
if (null !== $lifetime) {
ini_set('session.cookie_lifetime', $lifetime);
}
if ($destroy) {
$this->metadataBag->stampNew();
}
$isRegenerated = session_regenerate_id($destroy);
$this->loadSession();
return $isRegenerated;
}
public function save()
{
session_write_close();
if (!$this->saveHandler->isWrapper() && !$this->saveHandler->isSessionHandlerInterface()) {
$this->saveHandler->setActive(false);
}
$this->closed = true;
$this->started = false;
}
public function clear()
{
foreach ($this->bags as $bag) {
$bag->clear();
}
$_SESSION = array();
$this->loadSession();
}
public function registerBag(SessionBagInterface $bag)
{
$this->bags[$bag->getName()] = $bag;
}
public function getBag($name)
{
if (!isset($this->bags[$name])) {
throw new \InvalidArgumentException(sprintf('The SessionBagInterface %s is not registered.', $name));
}
if ($this->saveHandler->isActive() && !$this->started) {
$this->loadSession();
} elseif (!$this->started) {
$this->start();
}
return $this->bags[$name];
}
public function setMetadataBag(MetadataBag $metaBag = null)
{
if (null === $metaBag) {
$metaBag = new MetadataBag();
}
$this->metadataBag = $metaBag;
}
public function getMetadataBag()
{
return $this->metadataBag;
}
public function isStarted()
{
return $this->started;
}
public function setOptions(array $options)
{
$validOptions = array_flip(array('cache_limiter','cookie_domain','cookie_httponly','cookie_lifetime','cookie_path','cookie_secure','entropy_file','entropy_length','gc_divisor','gc_maxlifetime','gc_probability','hash_bits_per_character','hash_function','name','referer_check','serialize_handler','use_cookies','use_only_cookies','use_trans_sid','upload_progress.enabled','upload_progress.cleanup','upload_progress.prefix','upload_progress.name','upload_progress.freq','upload_progress.min-freq','url_rewriter.tags',
));
foreach ($options as $key => $value) {
if (isset($validOptions[$key])) {
ini_set('session.'.$key, $value);
}
}
}
public function setSaveHandler($saveHandler = null)
{
if (!$saveHandler instanceof AbstractProxy &&
!$saveHandler instanceof NativeSessionHandler &&
!$saveHandler instanceof \SessionHandlerInterface &&
null !== $saveHandler) {
throw new \InvalidArgumentException('Must be instance of AbstractProxy or NativeSessionHandler; implement \SessionHandlerInterface; or be null.');
}
if (!$saveHandler instanceof AbstractProxy && $saveHandler instanceof \SessionHandlerInterface) {
$saveHandler = new SessionHandlerProxy($saveHandler);
} elseif (!$saveHandler instanceof AbstractProxy) {
$saveHandler = PHP_VERSION_ID >= 50400 ?
new SessionHandlerProxy(new \SessionHandler()) : new NativeProxy();
}
$this->saveHandler = $saveHandler;
if ($this->saveHandler instanceof \SessionHandlerInterface) {
if (PHP_VERSION_ID >= 50400) {
session_set_save_handler($this->saveHandler, false);
} else {
session_set_save_handler(
array($this->saveHandler,'open'),
array($this->saveHandler,'close'),
array($this->saveHandler,'read'),
array($this->saveHandler,'write'),
array($this->saveHandler,'destroy'),
array($this->saveHandler,'gc')
);
}
}
}
protected function loadSession(array &$session = null)
{
if (null === $session) {
$session = &$_SESSION;
}
$bags = array_merge($this->bags, array($this->metadataBag));
foreach ($bags as $bag) {
$key = $bag->getStorageKey();
$session[$key] = isset($session[$key]) ? $session[$key] : array();
$bag->initialize($session[$key]);
}
$this->started = true;
$this->closed = false;
}
}
}
namespace Symfony\Component\HttpFoundation\Session\Storage
{
use Symfony\Component\HttpFoundation\Session\Storage\Proxy\AbstractProxy;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeSessionHandler;
class PhpBridgeSessionStorage extends NativeSessionStorage
{
public function __construct($handler = null, MetadataBag $metaBag = null)
{
$this->setMetadataBag($metaBag);
$this->setSaveHandler($handler);
}
public function start()
{
if ($this->started) {
return true;
}
$this->loadSession();
if (!$this->saveHandler->isWrapper() && !$this->saveHandler->isSessionHandlerInterface()) {
$this->saveHandler->setActive(true);
}
return true;
}
public function clear()
{
foreach ($this->bags as $bag) {
$bag->clear();
}
$this->loadSession();
}
}
}
namespace Symfony\Component\HttpFoundation\Session\Storage\Handler
{
if (PHP_VERSION_ID >= 50400) {
class NativeSessionHandler extends \SessionHandler
{
}
} else {
class NativeSessionHandler
{
}
}
}
namespace Symfony\Component\HttpFoundation\Session\Storage\Handler
{
class NativeFileSessionHandler extends NativeSessionHandler
{
public function __construct($savePath = null)
{
if (null === $savePath) {
$savePath = ini_get('session.save_path');
}
$baseDir = $savePath;
if ($count = substr_count($savePath,';')) {
if ($count > 2) {
throw new \InvalidArgumentException(sprintf('Invalid argument $savePath \'%s\'', $savePath));
}
$baseDir = ltrim(strrchr($savePath,';'),';');
}
if ($baseDir && !is_dir($baseDir) && !@mkdir($baseDir, 0777, true) && !is_dir($baseDir)) {
throw new \RuntimeException(sprintf('Session Storage was not able to create directory "%s"', $baseDir));
}
ini_set('session.save_path', $savePath);
ini_set('session.save_handler','files');
}
}
}
namespace Symfony\Component\HttpFoundation\Session\Storage\Proxy
{
abstract class AbstractProxy
{
protected $wrapper = false;
protected $active = false;
protected $saveHandlerName;
public function getSaveHandlerName()
{
return $this->saveHandlerName;
}
public function isSessionHandlerInterface()
{
return $this instanceof \SessionHandlerInterface;
}
public function isWrapper()
{
return $this->wrapper;
}
public function isActive()
{
if (PHP_VERSION_ID >= 50400) {
return $this->active = \PHP_SESSION_ACTIVE === session_status();
}
return $this->active;
}
public function setActive($flag)
{
if (PHP_VERSION_ID >= 50400) {
throw new \LogicException('This method is disabled in PHP 5.4.0+');
}
$this->active = (bool) $flag;
}
public function getId()
{
return session_id();
}
public function setId($id)
{
if ($this->isActive()) {
throw new \LogicException('Cannot change the ID of an active session');
}
session_id($id);
}
public function getName()
{
return session_name();
}
public function setName($name)
{
if ($this->isActive()) {
throw new \LogicException('Cannot change the name of an active session');
}
session_name($name);
}
}
}
namespace Symfony\Component\HttpFoundation\Session\Storage\Proxy
{
class SessionHandlerProxy extends AbstractProxy implements \SessionHandlerInterface
{
protected $handler;
public function __construct(\SessionHandlerInterface $handler)
{
$this->handler = $handler;
$this->wrapper = ($handler instanceof \SessionHandler);
$this->saveHandlerName = $this->wrapper ? ini_get('session.save_handler') :'user';
}
public function open($savePath, $sessionName)
{
$return = (bool) $this->handler->open($savePath, $sessionName);
if (true === $return) {
$this->active = true;
}
return $return;
}
public function close()
{
$this->active = false;
return (bool) $this->handler->close();
}
public function read($sessionId)
{
return (string) $this->handler->read($sessionId);
}
public function write($sessionId, $data)
{
return (bool) $this->handler->write($sessionId, $data);
}
public function destroy($sessionId)
{
return (bool) $this->handler->destroy($sessionId);
}
public function gc($maxlifetime)
{
return (bool) $this->handler->gc($maxlifetime);
}
}
}
namespace Symfony\Component\HttpFoundation\Session
{
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;
interface SessionInterface
{
public function start();
public function getId();
public function setId($id);
public function getName();
public function setName($name);
public function invalidate($lifetime = null);
public function migrate($destroy = false, $lifetime = null);
public function save();
public function has($name);
public function get($name, $default = null);
public function set($name, $value);
public function all();
public function replace(array $attributes);
public function remove($name);
public function clear();
public function isStarted();
public function registerBag(SessionBagInterface $bag);
public function getBag($name);
public function getMetadataBag();
}
}
namespace Symfony\Component\HttpFoundation\Session
{
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
class Session implements SessionInterface, \IteratorAggregate, \Countable
{
protected $storage;
private $flashName;
private $attributeName;
public function __construct(SessionStorageInterface $storage = null, AttributeBagInterface $attributes = null, FlashBagInterface $flashes = null)
{
$this->storage = $storage ?: new NativeSessionStorage();
$attributes = $attributes ?: new AttributeBag();
$this->attributeName = $attributes->getName();
$this->registerBag($attributes);
$flashes = $flashes ?: new FlashBag();
$this->flashName = $flashes->getName();
$this->registerBag($flashes);
}
public function start()
{
return $this->storage->start();
}
public function has($name)
{
return $this->storage->getBag($this->attributeName)->has($name);
}
public function get($name, $default = null)
{
return $this->storage->getBag($this->attributeName)->get($name, $default);
}
public function set($name, $value)
{
$this->storage->getBag($this->attributeName)->set($name, $value);
}
public function all()
{
return $this->storage->getBag($this->attributeName)->all();
}
public function replace(array $attributes)
{
$this->storage->getBag($this->attributeName)->replace($attributes);
}
public function remove($name)
{
return $this->storage->getBag($this->attributeName)->remove($name);
}
public function clear()
{
$this->storage->getBag($this->attributeName)->clear();
}
public function isStarted()
{
return $this->storage->isStarted();
}
public function getIterator()
{
return new \ArrayIterator($this->storage->getBag($this->attributeName)->all());
}
public function count()
{
return count($this->storage->getBag($this->attributeName)->all());
}
public function invalidate($lifetime = null)
{
$this->storage->clear();
return $this->migrate(true, $lifetime);
}
public function migrate($destroy = false, $lifetime = null)
{
return $this->storage->regenerate($destroy, $lifetime);
}
public function save()
{
$this->storage->save();
}
public function getId()
{
return $this->storage->getId();
}
public function setId($id)
{
$this->storage->setId($id);
}
public function getName()
{
return $this->storage->getName();
}
public function setName($name)
{
$this->storage->setName($name);
}
public function getMetadataBag()
{
return $this->storage->getMetadataBag();
}
public function registerBag(SessionBagInterface $bag)
{
$this->storage->registerBag($bag);
}
public function getBag($name)
{
return $this->storage->getBag($name);
}
public function getFlashBag()
{
return $this->getBag($this->flashName);
}
}
}
namespace Symfony\Bundle\FrameworkBundle\Templating
{
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\SecurityContext;
class GlobalVariables
{
protected $container;
public function __construct(ContainerInterface $container)
{
$this->container = $container;
}
public function getSecurity()
{
@trigger_error('The '.__METHOD__.' method is deprecated since version 2.6 and will be removed in 3.0.', E_USER_DEPRECATED);
if ($this->container->has('security.context')) {
return $this->container->get('security.context');
}
}
public function getUser()
{
if (!$this->container->has('security.token_storage')) {
return;
}
$tokenStorage = $this->container->get('security.token_storage');
if (!$token = $tokenStorage->getToken()) {
return;
}
$user = $token->getUser();
if (!is_object($user)) {
return;
}
return $user;
}
public function getRequest()
{
if ($this->container->has('request_stack')) {
return $this->container->get('request_stack')->getCurrentRequest();
}
}
public function getSession()
{
if ($request = $this->getRequest()) {
return $request->getSession();
}
}
public function getEnvironment()
{
return $this->container->getParameter('kernel.environment');
}
public function getDebug()
{
return (bool) $this->container->getParameter('kernel.debug');
}
}
}
namespace Symfony\Component\Templating
{
interface TemplateReferenceInterface
{
public function all();
public function set($name, $value);
public function get($name);
public function getPath();
public function getLogicalName();
public function __toString();
}
}
namespace Symfony\Component\Templating
{
class TemplateReference implements TemplateReferenceInterface
{
protected $parameters;
public function __construct($name = null, $engine = null)
{
$this->parameters = array('name'=> $name,'engine'=> $engine,
);
}
public function __toString()
{
return $this->getLogicalName();
}
public function set($name, $value)
{
if (array_key_exists($name, $this->parameters)) {
$this->parameters[$name] = $value;
} else {
throw new \InvalidArgumentException(sprintf('The template does not support the "%s" parameter.', $name));
}
return $this;
}
public function get($name)
{
if (array_key_exists($name, $this->parameters)) {
return $this->parameters[$name];
}
throw new \InvalidArgumentException(sprintf('The template does not support the "%s" parameter.', $name));
}
public function all()
{
return $this->parameters;
}
public function getPath()
{
return $this->parameters['name'];
}
public function getLogicalName()
{
return $this->parameters['name'];
}
}
}
namespace Symfony\Bundle\FrameworkBundle\Templating
{
use Symfony\Component\Templating\TemplateReference as BaseTemplateReference;
class TemplateReference extends BaseTemplateReference
{
public function __construct($bundle = null, $controller = null, $name = null, $format = null, $engine = null)
{
$this->parameters = array('bundle'=> $bundle,'controller'=> $controller,'name'=> $name,'format'=> $format,'engine'=> $engine,
);
}
public function getPath()
{
$controller = str_replace('\\','/', $this->get('controller'));
$path = (empty($controller) ?'': $controller.'/').$this->get('name').'.'.$this->get('format').'.'.$this->get('engine');
return empty($this->parameters['bundle']) ?'views/'.$path :'@'.$this->get('bundle').'/Resources/views/'.$path;
}
public function getLogicalName()
{
return sprintf('%s:%s:%s.%s.%s', $this->parameters['bundle'], $this->parameters['controller'], $this->parameters['name'], $this->parameters['format'], $this->parameters['engine']);
}
}
}
namespace Symfony\Component\Templating
{
interface TemplateNameParserInterface
{
public function parse($name);
}
}
namespace Symfony\Component\Templating
{
class TemplateNameParser implements TemplateNameParserInterface
{
public function parse($name)
{
if ($name instanceof TemplateReferenceInterface) {
return $name;
}
$engine = null;
if (false !== $pos = strrpos($name,'.')) {
$engine = substr($name, $pos + 1);
}
return new TemplateReference($name, $engine);
}
}
}
namespace Symfony\Bundle\FrameworkBundle\Templating
{
use Symfony\Component\Templating\TemplateReferenceInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Templating\TemplateNameParser as BaseTemplateNameParser;
class TemplateNameParser extends BaseTemplateNameParser
{
protected $kernel;
protected $cache = array();
public function __construct(KernelInterface $kernel)
{
$this->kernel = $kernel;
}
public function parse($name)
{
if ($name instanceof TemplateReferenceInterface) {
return $name;
} elseif (isset($this->cache[$name])) {
return $this->cache[$name];
}
$name = str_replace(':/',':', preg_replace('#/{2,}#','/', str_replace('\\','/', $name)));
if (false !== strpos($name,'..')) {
throw new \RuntimeException(sprintf('Template name "%s" contains invalid characters.', $name));
}
if (!preg_match('/^([^:]*):([^:]*):(.+)\.([^\.]+)\.([^\.]+)$/', $name, $matches)) {
return parent::parse($name);
}
$template = new TemplateReference($matches[1], $matches[2], $matches[3], $matches[4], $matches[5]);
if ($template->get('bundle')) {
try {
$this->kernel->getBundle($template->get('bundle'));
} catch (\Exception $e) {
throw new \InvalidArgumentException(sprintf('Template name "%s" is not valid.', $name), 0, $e);
}
}
return $this->cache[$name] = $template;
}
}
}
namespace Symfony\Component\Config
{
interface FileLocatorInterface
{
public function locate($name, $currentPath = null, $first = true);
}
}
namespace Symfony\Bundle\FrameworkBundle\Templating\Loader
{
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Templating\TemplateReferenceInterface;
class TemplateLocator implements FileLocatorInterface
{
protected $locator;
protected $cache;
public function __construct(FileLocatorInterface $locator, $cacheDir = null)
{
if (null !== $cacheDir && is_file($cache = $cacheDir.'/templates.php')) {
$this->cache = require $cache;
}
$this->locator = $locator;
}
protected function getCacheKey($template)
{
return $template->getLogicalName();
}
public function locate($template, $currentPath = null, $first = true)
{
if (!$template instanceof TemplateReferenceInterface) {
throw new \InvalidArgumentException('The template must be an instance of TemplateReferenceInterface.');
}
$key = $this->getCacheKey($template);
if (isset($this->cache[$key])) {
return $this->cache[$key];
}
try {
return $this->cache[$key] = $this->locator->locate($template->getPath(), $currentPath);
} catch (\InvalidArgumentException $e) {
throw new \InvalidArgumentException(sprintf('Unable to find template "%s" : "%s".', $template, $e->getMessage()), 0, $e);
}
}
}
}
namespace Symfony\Component\Templating\Storage
{
abstract class Storage
{
protected $template;
public function __construct($template)
{
$this->template = $template;
}
public function __toString()
{
return (string) $this->template;
}
abstract public function getContent();
}
}
namespace Symfony\Component\Templating\Storage
{
class FileStorage extends Storage
{
public function getContent()
{
return file_get_contents($this->template);
}
}
}
namespace Symfony\Component\Templating
{
interface EngineInterface
{
public function render($name, array $parameters = array());
public function exists($name);
public function supports($name);
}
}
namespace Symfony\Bundle\FrameworkBundle\Templating
{
use Symfony\Component\Templating\EngineInterface as BaseEngineInterface;
use Symfony\Component\HttpFoundation\Response;
interface EngineInterface extends BaseEngineInterface
{
public function renderResponse($view, array $parameters = array(), Response $response = null);
}
}
namespace Symfony\Component\Templating
{
use Symfony\Component\Templating\Storage\Storage;
use Symfony\Component\Templating\Storage\FileStorage;
use Symfony\Component\Templating\Storage\StringStorage;
use Symfony\Component\Templating\Helper\HelperInterface;
use Symfony\Component\Templating\Loader\LoaderInterface;
class PhpEngine implements EngineInterface, \ArrayAccess
{
protected $loader;
protected $current;
protected $helpers = array();
protected $parents = array();
protected $stack = array();
protected $charset ='UTF-8';
protected $cache = array();
protected $escapers = array();
protected static $escaperCache = array();
protected $globals = array();
protected $parser;
private $evalTemplate;
private $evalParameters;
public function __construct(TemplateNameParserInterface $parser, LoaderInterface $loader, array $helpers = array())
{
$this->parser = $parser;
$this->loader = $loader;
$this->addHelpers($helpers);
$this->initializeEscapers();
foreach ($this->escapers as $context => $escaper) {
$this->setEscaper($context, $escaper);
}
}
public function render($name, array $parameters = array())
{
$storage = $this->load($name);
$key = hash('sha256', serialize($storage));
$this->current = $key;
$this->parents[$key] = null;
$parameters = array_replace($this->getGlobals(), $parameters);
if (false === $content = $this->evaluate($storage, $parameters)) {
throw new \RuntimeException(sprintf('The template "%s" cannot be rendered.', $this->parser->parse($name)));
}
if ($this->parents[$key]) {
$slots = $this->get('slots');
$this->stack[] = $slots->get('_content');
$slots->set('_content', $content);
$content = $this->render($this->parents[$key], $parameters);
$slots->set('_content', array_pop($this->stack));
}
return $content;
}
public function exists($name)
{
try {
$this->load($name);
} catch (\InvalidArgumentException $e) {
return false;
}
return true;
}
public function supports($name)
{
$template = $this->parser->parse($name);
return'php'=== $template->get('engine');
}
protected function evaluate(Storage $template, array $parameters = array())
{
$this->evalTemplate = $template;
$this->evalParameters = $parameters;
unset($template, $parameters);
if (isset($this->evalParameters['this'])) {
throw new \InvalidArgumentException('Invalid parameter (this)');
}
if (isset($this->evalParameters['view'])) {
throw new \InvalidArgumentException('Invalid parameter (view)');
}
$view = $this;
if ($this->evalTemplate instanceof FileStorage) {
extract($this->evalParameters, EXTR_SKIP);
$this->evalParameters = null;
ob_start();
require $this->evalTemplate;
$this->evalTemplate = null;
return ob_get_clean();
} elseif ($this->evalTemplate instanceof StringStorage) {
extract($this->evalParameters, EXTR_SKIP);
$this->evalParameters = null;
ob_start();
eval('; ?>'.$this->evalTemplate.'<?php ;');
$this->evalTemplate = null;
return ob_get_clean();
}
return false;
}
public function offsetGet($name)
{
return $this->get($name);
}
public function offsetExists($name)
{
return isset($this->helpers[$name]);
}
public function offsetSet($name, $value)
{
$this->set($name, $value);
}
public function offsetUnset($name)
{
throw new \LogicException(sprintf('You can\'t unset a helper (%s).', $name));
}
public function addHelpers(array $helpers)
{
foreach ($helpers as $alias => $helper) {
$this->set($helper, is_int($alias) ? null : $alias);
}
}
public function setHelpers(array $helpers)
{
$this->helpers = array();
$this->addHelpers($helpers);
}
public function set(HelperInterface $helper, $alias = null)
{
$this->helpers[$helper->getName()] = $helper;
if (null !== $alias) {
$this->helpers[$alias] = $helper;
}
$helper->setCharset($this->charset);
}
public function has($name)
{
return isset($this->helpers[$name]);
}
public function get($name)
{
if (!isset($this->helpers[$name])) {
throw new \InvalidArgumentException(sprintf('The helper "%s" is not defined.', $name));
}
return $this->helpers[$name];
}
public function extend($template)
{
$this->parents[$this->current] = $template;
}
public function escape($value, $context ='html')
{
if (is_numeric($value)) {
return $value;
}
if (is_scalar($value)) {
if (!isset(self::$escaperCache[$context][$value])) {
self::$escaperCache[$context][$value] = call_user_func($this->getEscaper($context), $value);
}
return self::$escaperCache[$context][$value];
}
return call_user_func($this->getEscaper($context), $value);
}
public function setCharset($charset)
{
$this->charset = $charset;
foreach ($this->helpers as $helper) {
$helper->setCharset($this->charset);
}
}
public function getCharset()
{
return $this->charset;
}
public function setEscaper($context, $escaper)
{
$this->escapers[$context] = $escaper;
self::$escaperCache[$context] = array();
}
public function getEscaper($context)
{
if (!isset($this->escapers[$context])) {
throw new \InvalidArgumentException(sprintf('No registered escaper for context "%s".', $context));
}
return $this->escapers[$context];
}
public function addGlobal($name, $value)
{
$this->globals[$name] = $value;
}
public function getGlobals()
{
return $this->globals;
}
protected function initializeEscapers()
{
$that = $this;
if (PHP_VERSION_ID >= 50400) {
$flags = ENT_QUOTES | ENT_SUBSTITUTE;
} else {
$flags = ENT_QUOTES;
}
$this->escapers = array('html'=>
function ($value) use ($that, $flags) {
return is_string($value) ? htmlspecialchars($value, $flags, $that->getCharset(), false) : $value;
},'js'=>
function ($value) use ($that) {
if ('UTF-8'!= $that->getCharset()) {
$value = $that->convertEncoding($value,'UTF-8', $that->getCharset());
}
$callback = function ($matches) use ($that) {
$char = $matches[0];
if (!isset($char[1])) {
return'\\x'.substr('00'.bin2hex($char), -2);
}
$char = $that->convertEncoding($char,'UTF-16BE','UTF-8');
return'\\u'.substr('0000'.bin2hex($char), -4);
};
if (null === $value = preg_replace_callback('#[^\p{L}\p{N} ]#u', $callback, $value)) {
throw new \InvalidArgumentException('The string to escape is not a valid UTF-8 string.');
}
if ('UTF-8'!= $that->getCharset()) {
$value = $that->convertEncoding($value, $that->getCharset(),'UTF-8');
}
return $value;
},
);
self::$escaperCache = array();
}
public function convertEncoding($string, $to, $from)
{
if (function_exists('mb_convert_encoding')) {
return mb_convert_encoding($string, $to, $from);
} elseif (function_exists('iconv')) {
return iconv($from, $to, $string);
}
throw new \RuntimeException('No suitable convert encoding function (use UTF-8 as your encoding or install the iconv or mbstring extension).');
}
public function getLoader()
{
return $this->loader;
}
protected function load($name)
{
$template = $this->parser->parse($name);
$key = $template->getLogicalName();
if (isset($this->cache[$key])) {
return $this->cache[$key];
}
$storage = $this->loader->load($template);
if (false === $storage) {
throw new \InvalidArgumentException(sprintf('The template "%s" does not exist.', $template));
}
return $this->cache[$key] = $storage;
}
}
}
namespace Symfony\Bundle\FrameworkBundle\Templating
{
use Symfony\Component\Templating\PhpEngine as BasePhpEngine;
use Symfony\Component\Templating\Loader\LoaderInterface;
use Symfony\Component\Templating\TemplateNameParserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
class PhpEngine extends BasePhpEngine implements EngineInterface
{
protected $container;
public function __construct(TemplateNameParserInterface $parser, ContainerInterface $container, LoaderInterface $loader, GlobalVariables $globals = null)
{
$this->container = $container;
parent::__construct($parser, $loader);
if (null !== $globals) {
$this->addGlobal('app', $globals);
}
}
public function get($name)
{
if (!isset($this->helpers[$name])) {
throw new \InvalidArgumentException(sprintf('The helper "%s" is not defined.', $name));
}
if (is_string($this->helpers[$name])) {
$this->helpers[$name] = $this->container->get($this->helpers[$name]);
$this->helpers[$name]->setCharset($this->charset);
}
return $this->helpers[$name];
}
public function setHelpers(array $helpers)
{
$this->helpers = $helpers;
}
public function renderResponse($view, array $parameters = array(), Response $response = null)
{
if (null === $response) {
$response = new Response();
}
$response->setContent($this->render($view, $parameters));
return $response;
}
}
}
namespace Symfony\Component\Templating\Loader
{
use Symfony\Component\Templating\TemplateReferenceInterface;
use Symfony\Component\Templating\Storage\Storage;
interface LoaderInterface
{
public function load(TemplateReferenceInterface $template);
public function isFresh(TemplateReferenceInterface $template, $time);
}
}
namespace Symfony\Bundle\FrameworkBundle\Templating\Loader
{
use Symfony\Component\Templating\Storage\FileStorage;
use Symfony\Component\Templating\Loader\LoaderInterface;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Templating\TemplateReferenceInterface;
class FilesystemLoader implements LoaderInterface
{
protected $locator;
public function __construct(FileLocatorInterface $locator)
{
$this->locator = $locator;
}
public function load(TemplateReferenceInterface $template)
{
try {
$file = $this->locator->locate($template);
} catch (\InvalidArgumentException $e) {
return false;
}
return new FileStorage($file);
}
public function isFresh(TemplateReferenceInterface $template, $time)
{
if (false === $storage = $this->load($template)) {
return false;
}
if (!is_readable((string) $storage)) {
return false;
}
return filemtime((string) $storage) < $time;
}
}
}
namespace Symfony\Component\Routing
{
interface RequestContextAwareInterface
{
public function setContext(RequestContext $context);
public function getContext();
}
}
namespace Symfony\Component\Routing\Generator
{
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RequestContextAwareInterface;
interface UrlGeneratorInterface extends RequestContextAwareInterface
{
const ABSOLUTE_URL = true;
const ABSOLUTE_PATH = false;
const RELATIVE_PATH ='relative';
const NETWORK_PATH ='network';
public function generate($name, $parameters = array(), $referenceType = self::ABSOLUTE_PATH);
}
}
namespace Symfony\Component\Routing\Generator
{
interface ConfigurableRequirementsInterface
{
public function setStrictRequirements($enabled);
public function isStrictRequirements();
}
}
namespace Symfony\Component\Routing\Generator
{
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Psr\Log\LoggerInterface;
class UrlGenerator implements UrlGeneratorInterface, ConfigurableRequirementsInterface
{
protected $routes;
protected $context;
protected $strictRequirements = true;
protected $logger;
protected $decodedChars = array('%2F'=>'/','%40'=>'@','%3A'=>':','%3B'=>';','%2C'=>',','%3D'=>'=','%2B'=>'+','%21'=>'!','%2A'=>'*','%7C'=>'|',
);
public function __construct(RouteCollection $routes, RequestContext $context, LoggerInterface $logger = null)
{
$this->routes = $routes;
$this->context = $context;
$this->logger = $logger;
}
public function setContext(RequestContext $context)
{
$this->context = $context;
}
public function getContext()
{
return $this->context;
}
public function setStrictRequirements($enabled)
{
$this->strictRequirements = null === $enabled ? null : (bool) $enabled;
}
public function isStrictRequirements()
{
return $this->strictRequirements;
}
public function generate($name, $parameters = array(), $referenceType = self::ABSOLUTE_PATH)
{
if (null === $route = $this->routes->get($name)) {
throw new RouteNotFoundException(sprintf('Unable to generate a URL for the named route "%s" as such route does not exist.', $name));
}
$compiledRoute = $route->compile();
return $this->doGenerate($compiledRoute->getVariables(), $route->getDefaults(), $route->getRequirements(), $compiledRoute->getTokens(), $parameters, $name, $referenceType, $compiledRoute->getHostTokens(), $route->getSchemes());
}
protected function doGenerate($variables, $defaults, $requirements, $tokens, $parameters, $name, $referenceType, $hostTokens, array $requiredSchemes = array())
{
$variables = array_flip($variables);
$mergedParams = array_replace($defaults, $this->context->getParameters(), $parameters);
if ($diff = array_diff_key($variables, $mergedParams)) {
throw new MissingMandatoryParametersException(sprintf('Some mandatory parameters are missing ("%s") to generate a URL for route "%s".', implode('", "', array_keys($diff)), $name));
}
$url ='';
$optional = true;
foreach ($tokens as $token) {
if ('variable'=== $token[0]) {
if (!$optional || !array_key_exists($token[3], $defaults) || null !== $mergedParams[$token[3]] && (string) $mergedParams[$token[3]] !== (string) $defaults[$token[3]]) {
if (null !== $this->strictRequirements && !preg_match('#^'.$token[2].'$#', $mergedParams[$token[3]])) {
$message = sprintf('Parameter "%s" for route "%s" must match "%s" ("%s" given) to generate a corresponding URL.', $token[3], $name, $token[2], $mergedParams[$token[3]]);
if ($this->strictRequirements) {
throw new InvalidParameterException($message);
}
if ($this->logger) {
$this->logger->error($message);
}
return;
}
$url = $token[1].$mergedParams[$token[3]].$url;
$optional = false;
}
} else {
$url = $token[1].$url;
$optional = false;
}
}
if (''=== $url) {
$url ='/';
}
$url = strtr(rawurlencode($url), $this->decodedChars);
$url = strtr($url, array('/../'=>'/%2E%2E/','/./'=>'/%2E/'));
if ('/..'=== substr($url, -3)) {
$url = substr($url, 0, -2).'%2E%2E';
} elseif ('/.'=== substr($url, -2)) {
$url = substr($url, 0, -1).'%2E';
}
$schemeAuthority ='';
if ($host = $this->context->getHost()) {
$scheme = $this->context->getScheme();
if ($requiredSchemes) {
$schemeMatched = false;
foreach ($requiredSchemes as $requiredScheme) {
if ($scheme === $requiredScheme) {
$schemeMatched = true;
break;
}
}
if (!$schemeMatched) {
$referenceType = self::ABSOLUTE_URL;
$scheme = current($requiredSchemes);
}
} elseif (isset($requirements['_scheme']) && ($req = strtolower($requirements['_scheme'])) && $scheme !== $req) {
$referenceType = self::ABSOLUTE_URL;
$scheme = $req;
}
if ($hostTokens) {
$routeHost ='';
foreach ($hostTokens as $token) {
if ('variable'=== $token[0]) {
if (null !== $this->strictRequirements && !preg_match('#^'.$token[2].'$#i', $mergedParams[$token[3]])) {
$message = sprintf('Parameter "%s" for route "%s" must match "%s" ("%s" given) to generate a corresponding URL.', $token[3], $name, $token[2], $mergedParams[$token[3]]);
if ($this->strictRequirements) {
throw new InvalidParameterException($message);
}
if ($this->logger) {
$this->logger->error($message);
}
return;
}
$routeHost = $token[1].$mergedParams[$token[3]].$routeHost;
} else {
$routeHost = $token[1].$routeHost;
}
}
if ($routeHost !== $host) {
$host = $routeHost;
if (self::ABSOLUTE_URL !== $referenceType) {
$referenceType = self::NETWORK_PATH;
}
}
}
if (self::ABSOLUTE_URL === $referenceType || self::NETWORK_PATH === $referenceType) {
$port ='';
if ('http'=== $scheme && 80 != $this->context->getHttpPort()) {
$port =':'.$this->context->getHttpPort();
} elseif ('https'=== $scheme && 443 != $this->context->getHttpsPort()) {
$port =':'.$this->context->getHttpsPort();
}
$schemeAuthority = self::NETWORK_PATH === $referenceType ?'//': "$scheme://";
$schemeAuthority .= $host.$port;
}
}
if (self::RELATIVE_PATH === $referenceType) {
$url = self::getRelativePath($this->context->getPathInfo(), $url);
} else {
$url = $schemeAuthority.$this->context->getBaseUrl().$url;
}
$extra = array_diff_key($parameters, $variables, $defaults);
if ($extra && $query = http_build_query($extra,'','&')) {
$url .='?'.strtr($query, array('%2F'=>'/'));
}
return $url;
}
public static function getRelativePath($basePath, $targetPath)
{
if ($basePath === $targetPath) {
return'';
}
$sourceDirs = explode('/', isset($basePath[0]) &&'/'=== $basePath[0] ? substr($basePath, 1) : $basePath);
$targetDirs = explode('/', isset($targetPath[0]) &&'/'=== $targetPath[0] ? substr($targetPath, 1) : $targetPath);
array_pop($sourceDirs);
$targetFile = array_pop($targetDirs);
foreach ($sourceDirs as $i => $dir) {
if (isset($targetDirs[$i]) && $dir === $targetDirs[$i]) {
unset($sourceDirs[$i], $targetDirs[$i]);
} else {
break;
}
}
$targetDirs[] = $targetFile;
$path = str_repeat('../', count($sourceDirs)).implode('/', $targetDirs);
return''=== $path ||'/'=== $path[0]
|| false !== ($colonPos = strpos($path,':')) && ($colonPos < ($slashPos = strpos($path,'/')) || false === $slashPos)
? "./$path" : $path;
}
}
}
namespace Symfony\Component\Routing
{
use Symfony\Component\HttpFoundation\Request;
class RequestContext
{
private $baseUrl;
private $pathInfo;
private $method;
private $host;
private $scheme;
private $httpPort;
private $httpsPort;
private $queryString;
private $parameters = array();
public function __construct($baseUrl ='', $method ='GET', $host ='localhost', $scheme ='http', $httpPort = 80, $httpsPort = 443, $path ='/', $queryString ='')
{
$this->setBaseUrl($baseUrl);
$this->setMethod($method);
$this->setHost($host);
$this->setScheme($scheme);
$this->setHttpPort($httpPort);
$this->setHttpsPort($httpsPort);
$this->setPathInfo($path);
$this->setQueryString($queryString);
}
public function fromRequest(Request $request)
{
$this->setBaseUrl($request->getBaseUrl());
$this->setPathInfo($request->getPathInfo());
$this->setMethod($request->getMethod());
$this->setHost($request->getHost());
$this->setScheme($request->getScheme());
$this->setHttpPort($request->isSecure() ? $this->httpPort : $request->getPort());
$this->setHttpsPort($request->isSecure() ? $request->getPort() : $this->httpsPort);
$this->setQueryString($request->server->get('QUERY_STRING',''));
return $this;
}
public function getBaseUrl()
{
return $this->baseUrl;
}
public function setBaseUrl($baseUrl)
{
$this->baseUrl = $baseUrl;
return $this;
}
public function getPathInfo()
{
return $this->pathInfo;
}
public function setPathInfo($pathInfo)
{
$this->pathInfo = $pathInfo;
return $this;
}
public function getMethod()
{
return $this->method;
}
public function setMethod($method)
{
$this->method = strtoupper($method);
return $this;
}
public function getHost()
{
return $this->host;
}
public function setHost($host)
{
$this->host = strtolower($host);
return $this;
}
public function getScheme()
{
return $this->scheme;
}
public function setScheme($scheme)
{
$this->scheme = strtolower($scheme);
return $this;
}
public function getHttpPort()
{
return $this->httpPort;
}
public function setHttpPort($httpPort)
{
$this->httpPort = (int) $httpPort;
return $this;
}
public function getHttpsPort()
{
return $this->httpsPort;
}
public function setHttpsPort($httpsPort)
{
$this->httpsPort = (int) $httpsPort;
return $this;
}
public function getQueryString()
{
return $this->queryString;
}
public function setQueryString($queryString)
{
$this->queryString = (string) $queryString;
return $this;
}
public function getParameters()
{
return $this->parameters;
}
public function setParameters(array $parameters)
{
$this->parameters = $parameters;
return $this;
}
public function getParameter($name)
{
return isset($this->parameters[$name]) ? $this->parameters[$name] : null;
}
public function hasParameter($name)
{
return array_key_exists($name, $this->parameters);
}
public function setParameter($name, $parameter)
{
$this->parameters[$name] = $parameter;
return $this;
}
}
}
namespace Symfony\Component\Routing\Matcher
{
use Symfony\Component\Routing\RequestContextAwareInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
interface UrlMatcherInterface extends RequestContextAwareInterface
{
public function match($pathinfo);
}
}
namespace Symfony\Component\Routing
{
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
interface RouterInterface extends UrlMatcherInterface, UrlGeneratorInterface
{
public function getRouteCollection();
}
}
namespace Symfony\Component\Routing\Matcher
{
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
interface RequestMatcherInterface
{
public function matchRequest(Request $request);
}
}
namespace Symfony\Component\Routing
{
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\ConfigCacheInterface;
use Symfony\Component\Config\ConfigCacheFactoryInterface;
use Symfony\Component\Config\ConfigCacheFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\ConfigurableRequirementsInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Generator\Dumper\GeneratorDumperInterface;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\Matcher\Dumper\MatcherDumperInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
class Router implements RouterInterface, RequestMatcherInterface
{
protected $matcher;
protected $generator;
protected $context;
protected $loader;
protected $collection;
protected $resource;
protected $options = array();
protected $logger;
private $configCacheFactory;
private $expressionLanguageProviders = array();
public function __construct(LoaderInterface $loader, $resource, array $options = array(), RequestContext $context = null, LoggerInterface $logger = null)
{
$this->loader = $loader;
$this->resource = $resource;
$this->logger = $logger;
$this->context = $context ?: new RequestContext();
$this->setOptions($options);
}
public function setOptions(array $options)
{
$this->options = array('cache_dir'=> null,'debug'=> false,'generator_class'=>'Symfony\\Component\\Routing\\Generator\\UrlGenerator','generator_base_class'=>'Symfony\\Component\\Routing\\Generator\\UrlGenerator','generator_dumper_class'=>'Symfony\\Component\\Routing\\Generator\\Dumper\\PhpGeneratorDumper','generator_cache_class'=>'ProjectUrlGenerator','matcher_class'=>'Symfony\\Component\\Routing\\Matcher\\UrlMatcher','matcher_base_class'=>'Symfony\\Component\\Routing\\Matcher\\UrlMatcher','matcher_dumper_class'=>'Symfony\\Component\\Routing\\Matcher\\Dumper\\PhpMatcherDumper','matcher_cache_class'=>'ProjectUrlMatcher','resource_type'=> null,'strict_requirements'=> true,
);
$invalid = array();
foreach ($options as $key => $value) {
if (array_key_exists($key, $this->options)) {
$this->options[$key] = $value;
} else {
$invalid[] = $key;
}
}
if ($invalid) {
throw new \InvalidArgumentException(sprintf('The Router does not support the following options: "%s".', implode('", "', $invalid)));
}
}
public function setOption($key, $value)
{
if (!array_key_exists($key, $this->options)) {
throw new \InvalidArgumentException(sprintf('The Router does not support the "%s" option.', $key));
}
$this->options[$key] = $value;
}
public function getOption($key)
{
if (!array_key_exists($key, $this->options)) {
throw new \InvalidArgumentException(sprintf('The Router does not support the "%s" option.', $key));
}
return $this->options[$key];
}
public function getRouteCollection()
{
if (null === $this->collection) {
$this->collection = $this->loader->load($this->resource, $this->options['resource_type']);
}
return $this->collection;
}
public function setContext(RequestContext $context)
{
$this->context = $context;
if (null !== $this->matcher) {
$this->getMatcher()->setContext($context);
}
if (null !== $this->generator) {
$this->getGenerator()->setContext($context);
}
}
public function getContext()
{
return $this->context;
}
public function setConfigCacheFactory(ConfigCacheFactoryInterface $configCacheFactory)
{
$this->configCacheFactory = $configCacheFactory;
}
public function generate($name, $parameters = array(), $referenceType = self::ABSOLUTE_PATH)
{
return $this->getGenerator()->generate($name, $parameters, $referenceType);
}
public function match($pathinfo)
{
return $this->getMatcher()->match($pathinfo);
}
public function matchRequest(Request $request)
{
$matcher = $this->getMatcher();
if (!$matcher instanceof RequestMatcherInterface) {
return $matcher->match($request->getPathInfo());
}
return $matcher->matchRequest($request);
}
public function getMatcher()
{
if (null !== $this->matcher) {
return $this->matcher;
}
if (null === $this->options['cache_dir'] || null === $this->options['matcher_cache_class']) {
$this->matcher = new $this->options['matcher_class']($this->getRouteCollection(), $this->context);
if (method_exists($this->matcher,'addExpressionLanguageProvider')) {
foreach ($this->expressionLanguageProviders as $provider) {
$this->matcher->addExpressionLanguageProvider($provider);
}
}
return $this->matcher;
}
$class = $this->options['matcher_cache_class'];
$baseClass = $this->options['matcher_base_class'];
$expressionLanguageProviders = $this->expressionLanguageProviders;
$that = $this;
$cache = $this->getConfigCacheFactory()->cache($this->options['cache_dir'].'/'.$class.'.php',
function (ConfigCacheInterface $cache) use ($that, $class, $baseClass, $expressionLanguageProviders) {
$dumper = $that->getMatcherDumperInstance();
if (method_exists($dumper,'addExpressionLanguageProvider')) {
foreach ($expressionLanguageProviders as $provider) {
$dumper->addExpressionLanguageProvider($provider);
}
}
$options = array('class'=> $class,'base_class'=> $baseClass,
);
$cache->write($dumper->dump($options), $that->getRouteCollection()->getResources());
}
);
require_once $cache->getPath();
return $this->matcher = new $class($this->context);
}
public function getGenerator()
{
if (null !== $this->generator) {
return $this->generator;
}
if (null === $this->options['cache_dir'] || null === $this->options['generator_cache_class']) {
$this->generator = new $this->options['generator_class']($this->getRouteCollection(), $this->context, $this->logger);
} else {
$class = $this->options['generator_cache_class'];
$baseClass = $this->options['generator_base_class'];
$that = $this; $cache = $this->getConfigCacheFactory()->cache($this->options['cache_dir'].'/'.$class.'.php',
function (ConfigCacheInterface $cache) use ($that, $class, $baseClass) {
$dumper = $that->getGeneratorDumperInstance();
$options = array('class'=> $class,'base_class'=> $baseClass,
);
$cache->write($dumper->dump($options), $that->getRouteCollection()->getResources());
}
);
require_once $cache->getPath();
$this->generator = new $class($this->context, $this->logger);
}
if ($this->generator instanceof ConfigurableRequirementsInterface) {
$this->generator->setStrictRequirements($this->options['strict_requirements']);
}
return $this->generator;
}
public function addExpressionLanguageProvider(ExpressionFunctionProviderInterface $provider)
{
$this->expressionLanguageProviders[] = $provider;
}
public function getGeneratorDumperInstance()
{
return new $this->options['generator_dumper_class']($this->getRouteCollection());
}
public function getMatcherDumperInstance()
{
return new $this->options['matcher_dumper_class']($this->getRouteCollection());
}
private function getConfigCacheFactory()
{
if (null === $this->configCacheFactory) {
$this->configCacheFactory = new ConfigCacheFactory($this->options['debug']);
}
return $this->configCacheFactory;
}
}
}
namespace Symfony\Component\Routing\Matcher
{
interface RedirectableUrlMatcherInterface
{
public function redirect($path, $route, $scheme = null);
}
}
namespace Symfony\Component\Routing\Matcher
{
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
class UrlMatcher implements UrlMatcherInterface, RequestMatcherInterface
{
const REQUIREMENT_MATCH = 0;
const REQUIREMENT_MISMATCH = 1;
const ROUTE_MATCH = 2;
protected $context;
protected $allow = array();
protected $routes;
protected $request;
protected $expressionLanguage;
protected $expressionLanguageProviders = array();
public function __construct(RouteCollection $routes, RequestContext $context)
{
$this->routes = $routes;
$this->context = $context;
}
public function setContext(RequestContext $context)
{
$this->context = $context;
}
public function getContext()
{
return $this->context;
}
public function match($pathinfo)
{
$this->allow = array();
if ($ret = $this->matchCollection(rawurldecode($pathinfo), $this->routes)) {
return $ret;
}
throw 0 < count($this->allow)
? new MethodNotAllowedException(array_unique($this->allow))
: new ResourceNotFoundException(sprintf('No routes found for "%s".', $pathinfo));
}
public function matchRequest(Request $request)
{
$this->request = $request;
$ret = $this->match($request->getPathInfo());
$this->request = null;
return $ret;
}
public function addExpressionLanguageProvider(ExpressionFunctionProviderInterface $provider)
{
$this->expressionLanguageProviders[] = $provider;
}
protected function matchCollection($pathinfo, RouteCollection $routes)
{
foreach ($routes as $name => $route) {
$compiledRoute = $route->compile();
if (''!== $compiledRoute->getStaticPrefix() && 0 !== strpos($pathinfo, $compiledRoute->getStaticPrefix())) {
continue;
}
if (!preg_match($compiledRoute->getRegex(), $pathinfo, $matches)) {
continue;
}
$hostMatches = array();
if ($compiledRoute->getHostRegex() && !preg_match($compiledRoute->getHostRegex(), $this->context->getHost(), $hostMatches)) {
continue;
}
if ($requiredMethods = $route->getMethods()) {
if ('HEAD'=== $method = $this->context->getMethod()) {
$method ='GET';
}
if (!in_array($method, $requiredMethods)) {
$this->allow = array_merge($this->allow, $requiredMethods);
continue;
}
}
$status = $this->handleRouteRequirements($pathinfo, $name, $route);
if (self::ROUTE_MATCH === $status[0]) {
return $status[1];
}
if (self::REQUIREMENT_MISMATCH === $status[0]) {
continue;
}
return $this->getAttributes($route, $name, array_replace($matches, $hostMatches));
}
}
protected function getAttributes(Route $route, $name, array $attributes)
{
$attributes['_route'] = $name;
return $this->mergeDefaults($attributes, $route->getDefaults());
}
protected function handleRouteRequirements($pathinfo, $name, Route $route)
{
if ($route->getCondition() && !$this->getExpressionLanguage()->evaluate($route->getCondition(), array('context'=> $this->context,'request'=> $this->request))) {
return array(self::REQUIREMENT_MISMATCH, null);
}
$scheme = $this->context->getScheme();
$status = $route->getSchemes() && !$route->hasScheme($scheme) ? self::REQUIREMENT_MISMATCH : self::REQUIREMENT_MATCH;
return array($status, null);
}
protected function mergeDefaults($params, $defaults)
{
foreach ($params as $key => $value) {
if (!is_int($key)) {
$defaults[$key] = $value;
}
}
return $defaults;
}
protected function getExpressionLanguage()
{
if (null === $this->expressionLanguage) {
if (!class_exists('Symfony\Component\ExpressionLanguage\ExpressionLanguage')) {
throw new \RuntimeException('Unable to use expressions as the Symfony ExpressionLanguage component is not installed.');
}
$this->expressionLanguage = new ExpressionLanguage(null, $this->expressionLanguageProviders);
}
return $this->expressionLanguage;
}
}
}
namespace Symfony\Component\Routing\Matcher
{
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Route;
abstract class RedirectableUrlMatcher extends UrlMatcher implements RedirectableUrlMatcherInterface
{
public function match($pathinfo)
{
try {
$parameters = parent::match($pathinfo);
} catch (ResourceNotFoundException $e) {
if ('/'=== substr($pathinfo, -1) || !in_array($this->context->getMethod(), array('HEAD','GET'))) {
throw $e;
}
try {
parent::match($pathinfo.'/');
return $this->redirect($pathinfo.'/', null);
} catch (ResourceNotFoundException $e2) {
throw $e;
}
}
return $parameters;
}
protected function handleRouteRequirements($pathinfo, $name, Route $route)
{
if ($route->getCondition() && !$this->getExpressionLanguage()->evaluate($route->getCondition(), array('context'=> $this->context,'request'=> $this->request))) {
return array(self::REQUIREMENT_MISMATCH, null);
}
$scheme = $this->context->getScheme();
$schemes = $route->getSchemes();
if ($schemes && !$route->hasScheme($scheme)) {
return array(self::ROUTE_MATCH, $this->redirect($pathinfo, $name, current($schemes)));
}
return array(self::REQUIREMENT_MATCH, null);
}
}
}
namespace Symfony\Bundle\FrameworkBundle\Routing
{
use Symfony\Component\Routing\Matcher\RedirectableUrlMatcher as BaseMatcher;
class RedirectableUrlMatcher extends BaseMatcher
{
public function redirect($path, $route, $scheme = null)
{
return array('_controller'=>'Symfony\\Bundle\\FrameworkBundle\\Controller\\RedirectController::urlRedirectAction','path'=> $path,'permanent'=> true,'scheme'=> $scheme,'httpPort'=> $this->context->getHttpPort(),'httpsPort'=> $this->context->getHttpsPort(),'_route'=> $route,
);
}
}
}
namespace Symfony\Component\HttpKernel\CacheWarmer
{
interface WarmableInterface
{
public function warmUp($cacheDir);
}
}
namespace Symfony\Bundle\FrameworkBundle\Routing
{
use Symfony\Component\Routing\Router as BaseRouter;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
class Router extends BaseRouter implements WarmableInterface
{
private $container;
public function __construct(ContainerInterface $container, $resource, array $options = array(), RequestContext $context = null)
{
$this->container = $container;
$this->resource = $resource;
$this->context = $context ?: new RequestContext();
$this->setOptions($options);
}
public function getRouteCollection()
{
if (null === $this->collection) {
$this->collection = $this->container->get('routing.loader')->load($this->resource, $this->options['resource_type']);
$this->resolveParameters($this->collection);
}
return $this->collection;
}
public function warmUp($cacheDir)
{
$currentDir = $this->getOption('cache_dir');
$this->setOption('cache_dir', $cacheDir);
$this->getMatcher();
$this->getGenerator();
$this->setOption('cache_dir', $currentDir);
}
private function resolveParameters(RouteCollection $collection)
{
foreach ($collection as $route) {
foreach ($route->getDefaults() as $name => $value) {
$route->setDefault($name, $this->resolve($value));
}
foreach ($route->getRequirements() as $name => $value) {
if ('_scheme'=== $name ||'_method'=== $name) {
continue; }
$route->setRequirement($name, $this->resolve($value));
}
$route->setPath($this->resolve($route->getPath()));
$route->setHost($this->resolve($route->getHost()));
$schemes = array();
foreach ($route->getSchemes() as $scheme) {
$schemes = array_merge($schemes, explode('|', $this->resolve($scheme)));
}
$route->setSchemes($schemes);
$methods = array();
foreach ($route->getMethods() as $method) {
$methods = array_merge($methods, explode('|', $this->resolve($method)));
}
$route->setMethods($methods);
$route->setCondition($this->resolve($route->getCondition()));
}
}
private function resolve($value)
{
if (is_array($value)) {
foreach ($value as $key => $val) {
$value[$key] = $this->resolve($val);
}
return $value;
}
if (!is_string($value)) {
return $value;
}
$container = $this->container;
$escapedValue = preg_replace_callback('/%%|%([^%\s]++)%/', function ($match) use ($container, $value) {
if (!isset($match[1])) {
return'%%';
}
$resolved = $container->getParameter($match[1]);
if (is_string($resolved) || is_numeric($resolved)) {
return (string) $resolved;
}
throw new RuntimeException(sprintf('The container parameter "%s", used in the route configuration value "%s", '.'must be a string or numeric, but it is of type %s.',
$match[1],
$value,
gettype($resolved)
)
);
}, $value);
return str_replace('%%','%', $escapedValue);
}
}
}
namespace Symfony\Component\Config
{
class FileLocator implements FileLocatorInterface
{
protected $paths;
public function __construct($paths = array())
{
$this->paths = (array) $paths;
}
public function locate($name, $currentPath = null, $first = true)
{
if (''== $name) {
throw new \InvalidArgumentException('An empty file name is not valid to be located.');
}
if ($this->isAbsolutePath($name)) {
if (!file_exists($name)) {
throw new \InvalidArgumentException(sprintf('The file "%s" does not exist.', $name));
}
return $name;
}
$paths = $this->paths;
if (null !== $currentPath) {
array_unshift($paths, $currentPath);
}
$paths = array_unique($paths);
$filepaths = array();
foreach ($paths as $path) {
if (file_exists($file = $path.DIRECTORY_SEPARATOR.$name)) {
if (true === $first) {
return $file;
}
$filepaths[] = $file;
}
}
if (!$filepaths) {
throw new \InvalidArgumentException(sprintf('The file "%s" does not exist (in: %s).', $name, implode(', ', $paths)));
}
return $filepaths;
}
private function isAbsolutePath($file)
{
if ($file[0] ==='/'|| $file[0] ==='\\'|| (strlen($file) > 3 && ctype_alpha($file[0])
&& $file[1] ===':'&& ($file[2] ==='\\'|| $file[2] ==='/')
)
|| null !== parse_url($file, PHP_URL_SCHEME)
) {
return true;
}
return false;
}
}
}
namespace Symfony\Component\EventDispatcher
{
class Event
{
private $propagationStopped = false;
private $dispatcher;
private $name;
public function isPropagationStopped()
{
return $this->propagationStopped;
}
public function stopPropagation()
{
$this->propagationStopped = true;
}
public function setDispatcher(EventDispatcherInterface $dispatcher)
{
$this->dispatcher = $dispatcher;
}
public function getDispatcher()
{
@trigger_error('The '.__METHOD__.' method is deprecated since version 2.4 and will be removed in 3.0. The event dispatcher instance can be received in the listener call instead.', E_USER_DEPRECATED);
return $this->dispatcher;
}
public function getName()
{
@trigger_error('The '.__METHOD__.' method is deprecated since version 2.4 and will be removed in 3.0. The event name can be received in the listener call instead.', E_USER_DEPRECATED);
return $this->name;
}
public function setName($name)
{
$this->name = $name;
}
}
}
namespace Symfony\Component\EventDispatcher
{
interface EventDispatcherInterface
{
public function dispatch($eventName, Event $event = null);
public function addListener($eventName, $listener, $priority = 0);
public function addSubscriber(EventSubscriberInterface $subscriber);
public function removeListener($eventName, $listener);
public function removeSubscriber(EventSubscriberInterface $subscriber);
public function getListeners($eventName = null);
public function hasListeners($eventName = null);
}
}
namespace Symfony\Component\EventDispatcher
{
class EventDispatcher implements EventDispatcherInterface
{
private $listeners = array();
private $sorted = array();
public function dispatch($eventName, Event $event = null)
{
if (null === $event) {
$event = new Event();
}
$event->setDispatcher($this);
$event->setName($eventName);
if ($listeners = $this->getListeners($eventName)) {
$this->doDispatch($listeners, $eventName, $event);
}
return $event;
}
public function getListeners($eventName = null)
{
if (null !== $eventName) {
if (!isset($this->listeners[$eventName])) {
return array();
}
if (!isset($this->sorted[$eventName])) {
$this->sortListeners($eventName);
}
return $this->sorted[$eventName];
}
foreach ($this->listeners as $eventName => $eventListeners) {
if (!isset($this->sorted[$eventName])) {
$this->sortListeners($eventName);
}
}
return array_filter($this->sorted);
}
public function hasListeners($eventName = null)
{
return (bool) count($this->getListeners($eventName));
}
public function addListener($eventName, $listener, $priority = 0)
{
$this->listeners[$eventName][$priority][] = $listener;
unset($this->sorted[$eventName]);
}
public function removeListener($eventName, $listener)
{
if (!isset($this->listeners[$eventName])) {
return;
}
foreach ($this->listeners[$eventName] as $priority => $listeners) {
if (false !== ($key = array_search($listener, $listeners, true))) {
unset($this->listeners[$eventName][$priority][$key], $this->sorted[$eventName]);
}
}
}
public function addSubscriber(EventSubscriberInterface $subscriber)
{
foreach ($subscriber->getSubscribedEvents() as $eventName => $params) {
if (is_string($params)) {
$this->addListener($eventName, array($subscriber, $params));
} elseif (is_string($params[0])) {
$this->addListener($eventName, array($subscriber, $params[0]), isset($params[1]) ? $params[1] : 0);
} else {
foreach ($params as $listener) {
$this->addListener($eventName, array($subscriber, $listener[0]), isset($listener[1]) ? $listener[1] : 0);
}
}
}
}
public function removeSubscriber(EventSubscriberInterface $subscriber)
{
foreach ($subscriber->getSubscribedEvents() as $eventName => $params) {
if (is_array($params) && is_array($params[0])) {
foreach ($params as $listener) {
$this->removeListener($eventName, array($subscriber, $listener[0]));
}
} else {
$this->removeListener($eventName, array($subscriber, is_string($params) ? $params : $params[0]));
}
}
}
protected function doDispatch($listeners, $eventName, Event $event)
{
foreach ($listeners as $listener) {
call_user_func($listener, $event, $eventName, $this);
if ($event->isPropagationStopped()) {
break;
}
}
}
private function sortListeners($eventName)
{
$this->sorted[$eventName] = array();
krsort($this->listeners[$eventName]);
$this->sorted[$eventName] = call_user_func_array('array_merge', $this->listeners[$eventName]);
}
}
}
namespace Symfony\Component\EventDispatcher
{
use Symfony\Component\DependencyInjection\ContainerInterface;
class ContainerAwareEventDispatcher extends EventDispatcher
{
private $container;
private $listenerIds = array();
private $listeners = array();
public function __construct(ContainerInterface $container)
{
$this->container = $container;
}
public function addListenerService($eventName, $callback, $priority = 0)
{
if (!is_array($callback) || 2 !== count($callback)) {
throw new \InvalidArgumentException('Expected an array("service", "method") argument');
}
$this->listenerIds[$eventName][] = array($callback[0], $callback[1], $priority);
}
public function removeListener($eventName, $listener)
{
$this->lazyLoad($eventName);
if (isset($this->listenerIds[$eventName])) {
foreach ($this->listenerIds[$eventName] as $i => $args) {
list($serviceId, $method, $priority) = $args;
$key = $serviceId.'.'.$method;
if (isset($this->listeners[$eventName][$key]) && $listener === array($this->listeners[$eventName][$key], $method)) {
unset($this->listeners[$eventName][$key]);
if (empty($this->listeners[$eventName])) {
unset($this->listeners[$eventName]);
}
unset($this->listenerIds[$eventName][$i]);
if (empty($this->listenerIds[$eventName])) {
unset($this->listenerIds[$eventName]);
}
}
}
}
parent::removeListener($eventName, $listener);
}
public function hasListeners($eventName = null)
{
if (null === $eventName) {
return (bool) count($this->listenerIds) || (bool) count($this->listeners);
}
if (isset($this->listenerIds[$eventName])) {
return true;
}
return parent::hasListeners($eventName);
}
public function getListeners($eventName = null)
{
if (null === $eventName) {
foreach ($this->listenerIds as $serviceEventName => $args) {
$this->lazyLoad($serviceEventName);
}
} else {
$this->lazyLoad($eventName);
}
return parent::getListeners($eventName);
}
public function addSubscriberService($serviceId, $class)
{
foreach ($class::getSubscribedEvents() as $eventName => $params) {
if (is_string($params)) {
$this->listenerIds[$eventName][] = array($serviceId, $params, 0);
} elseif (is_string($params[0])) {
$this->listenerIds[$eventName][] = array($serviceId, $params[0], isset($params[1]) ? $params[1] : 0);
} else {
foreach ($params as $listener) {
$this->listenerIds[$eventName][] = array($serviceId, $listener[0], isset($listener[1]) ? $listener[1] : 0);
}
}
}
}
public function getContainer()
{
return $this->container;
}
protected function lazyLoad($eventName)
{
if (isset($this->listenerIds[$eventName])) {
foreach ($this->listenerIds[$eventName] as $args) {
list($serviceId, $method, $priority) = $args;
$listener = $this->container->get($serviceId);
$key = $serviceId.'.'.$method;
if (!isset($this->listeners[$eventName][$key])) {
$this->addListener($eventName, array($listener, $method), $priority);
} elseif ($listener !== $this->listeners[$eventName][$key]) {
parent::removeListener($eventName, array($this->listeners[$eventName][$key], $method));
$this->addListener($eventName, array($listener, $method), $priority);
}
$this->listeners[$eventName][$key] = $listener;
}
}
}
}
}
namespace Symfony\Component\HttpKernel\EventListener
{
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
class ResponseListener implements EventSubscriberInterface
{
private $charset;
public function __construct($charset)
{
$this->charset = $charset;
}
public function onKernelResponse(FilterResponseEvent $event)
{
if (!$event->isMasterRequest()) {
return;
}
$response = $event->getResponse();
if (null === $response->getCharset()) {
$response->setCharset($this->charset);
}
$response->prepare($event->getRequest());
}
public static function getSubscribedEvents()
{
return array(
KernelEvents::RESPONSE =>'onKernelResponse',
);
}
}
}
namespace Symfony\Component\HttpKernel\EventListener
{
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RequestContextAwareInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
class RouterListener implements EventSubscriberInterface
{
private $matcher;
private $context;
private $logger;
private $request;
private $requestStack;
public function __construct($matcher, RequestContext $context = null, LoggerInterface $logger = null, RequestStack $requestStack = null)
{
if (!$matcher instanceof UrlMatcherInterface && !$matcher instanceof RequestMatcherInterface) {
throw new \InvalidArgumentException('Matcher must either implement UrlMatcherInterface or RequestMatcherInterface.');
}
if (null === $context && !$matcher instanceof RequestContextAwareInterface) {
throw new \InvalidArgumentException('You must either pass a RequestContext or the matcher must implement RequestContextAwareInterface.');
}
if (!$requestStack instanceof RequestStack) {
@trigger_error('The '.__METHOD__.' method now requires a RequestStack instance as '.__CLASS__.'::setRequest method will not be supported anymore in 3.0.', E_USER_DEPRECATED);
}
$this->matcher = $matcher;
$this->context = $context ?: $matcher->getContext();
$this->requestStack = $requestStack;
$this->logger = $logger;
}
public function setRequest(Request $request = null)
{
@trigger_error('The '.__METHOD__.' method is deprecated since version 2.4 and will be made private in 3.0.', E_USER_DEPRECATED);
$this->setCurrentRequest($request);
}
private function setCurrentRequest(Request $request = null)
{
if (null !== $request && $this->request !== $request) {
$this->context->fromRequest($request);
}
$this->request = $request;
}
public function onKernelFinishRequest(FinishRequestEvent $event)
{
if (null === $this->requestStack) {
return; }
$this->setCurrentRequest($this->requestStack->getParentRequest());
}
public function onKernelRequest(GetResponseEvent $event)
{
$request = $event->getRequest();
if (null !== $this->requestStack) {
$this->setCurrentRequest($request);
}
if ($request->attributes->has('_controller')) {
return;
}
try {
if ($this->matcher instanceof RequestMatcherInterface) {
$parameters = $this->matcher->matchRequest($request);
} else {
$parameters = $this->matcher->match($request->getPathInfo());
}
if (null !== $this->logger) {
$this->logger->info(sprintf('Matched route "%s".', isset($parameters['_route']) ? $parameters['_route'] :'n/a'), array('route_parameters'=> $parameters,'request_uri'=> $request->getUri(),
));
}
$request->attributes->add($parameters);
unset($parameters['_route'], $parameters['_controller']);
$request->attributes->set('_route_params', $parameters);
} catch (ResourceNotFoundException $e) {
$message = sprintf('No route found for "%s %s"', $request->getMethod(), $request->getPathInfo());
if ($referer = $request->headers->get('referer')) {
$message .= sprintf(' (from "%s")', $referer);
}
throw new NotFoundHttpException($message, $e);
} catch (MethodNotAllowedException $e) {
$message = sprintf('No route found for "%s %s": Method Not Allowed (Allow: %s)', $request->getMethod(), $request->getPathInfo(), implode(', ', $e->getAllowedMethods()));
throw new MethodNotAllowedHttpException($e->getAllowedMethods(), $message, $e);
}
}
public static function getSubscribedEvents()
{
return array(
KernelEvents::REQUEST => array(array('onKernelRequest', 32)),
KernelEvents::FINISH_REQUEST => array(array('onKernelFinishRequest', 0)),
);
}
}
}
namespace Symfony\Component\HttpKernel\Controller
{
use Symfony\Component\HttpFoundation\Request;
interface ControllerResolverInterface
{
public function getController(Request $request);
public function getArguments(Request $request, $controller);
}
}
namespace Symfony\Component\HttpKernel\Controller
{
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
class ControllerResolver implements ControllerResolverInterface
{
private $logger;
public function __construct(LoggerInterface $logger = null)
{
$this->logger = $logger;
}
public function getController(Request $request)
{
if (!$controller = $request->attributes->get('_controller')) {
if (null !== $this->logger) {
$this->logger->warning('Unable to look for the controller as the "_controller" parameter is missing.');
}
return false;
}
if (is_array($controller)) {
return $controller;
}
if (is_object($controller)) {
if (method_exists($controller,'__invoke')) {
return $controller;
}
throw new \InvalidArgumentException(sprintf('Controller "%s" for URI "%s" is not callable.', get_class($controller), $request->getPathInfo()));
}
if (false === strpos($controller,':')) {
if (method_exists($controller,'__invoke')) {
return $this->instantiateController($controller);
} elseif (function_exists($controller)) {
return $controller;
}
}
$callable = $this->createController($controller);
if (!is_callable($callable)) {
throw new \InvalidArgumentException(sprintf('Controller "%s" for URI "%s" is not callable.', $controller, $request->getPathInfo()));
}
return $callable;
}
public function getArguments(Request $request, $controller)
{
if (is_array($controller)) {
$r = new \ReflectionMethod($controller[0], $controller[1]);
} elseif (is_object($controller) && !$controller instanceof \Closure) {
$r = new \ReflectionObject($controller);
$r = $r->getMethod('__invoke');
} else {
$r = new \ReflectionFunction($controller);
}
return $this->doGetArguments($request, $controller, $r->getParameters());
}
protected function doGetArguments(Request $request, $controller, array $parameters)
{
$attributes = $request->attributes->all();
$arguments = array();
foreach ($parameters as $param) {
if (array_key_exists($param->name, $attributes)) {
$arguments[] = $attributes[$param->name];
} elseif ($param->getClass() && $param->getClass()->isInstance($request)) {
$arguments[] = $request;
} elseif ($param->isDefaultValueAvailable()) {
$arguments[] = $param->getDefaultValue();
} else {
if (is_array($controller)) {
$repr = sprintf('%s::%s()', get_class($controller[0]), $controller[1]);
} elseif (is_object($controller)) {
$repr = get_class($controller);
} else {
$repr = $controller;
}
throw new \RuntimeException(sprintf('Controller "%s" requires that you provide a value for the "$%s" argument (because there is no default value or because there is a non optional argument after this one).', $repr, $param->name));
}
}
return $arguments;
}
protected function createController($controller)
{
if (false === strpos($controller,'::')) {
throw new \InvalidArgumentException(sprintf('Unable to find controller "%s".', $controller));
}
list($class, $method) = explode('::', $controller, 2);
if (!class_exists($class)) {
throw new \InvalidArgumentException(sprintf('Class "%s" does not exist.', $class));
}
return array($this->instantiateController($class), $method);
}
protected function instantiateController($class)
{
return new $class();
}
}
}
namespace Symfony\Component\HttpKernel\Event
{
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\Event;
class KernelEvent extends Event
{
private $kernel;
private $request;
private $requestType;
public function __construct(HttpKernelInterface $kernel, Request $request, $requestType)
{
$this->kernel = $kernel;
$this->request = $request;
$this->requestType = $requestType;
}
public function getKernel()
{
return $this->kernel;
}
public function getRequest()
{
return $this->request;
}
public function getRequestType()
{
return $this->requestType;
}
public function isMasterRequest()
{
return HttpKernelInterface::MASTER_REQUEST === $this->requestType;
}
}
}
namespace Symfony\Component\HttpKernel\Event
{
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
class FilterControllerEvent extends KernelEvent
{
private $controller;
public function __construct(HttpKernelInterface $kernel, $controller, Request $request, $requestType)
{
parent::__construct($kernel, $request, $requestType);
$this->setController($controller);
}
public function getController()
{
return $this->controller;
}
public function setController($controller)
{
if (!is_callable($controller)) {
throw new \LogicException(sprintf('The controller must be a callable (%s given).', $this->varToString($controller)));
}
$this->controller = $controller;
}
private function varToString($var)
{
if (is_object($var)) {
return sprintf('Object(%s)', get_class($var));
}
if (is_array($var)) {
$a = array();
foreach ($var as $k => $v) {
$a[] = sprintf('%s => %s', $k, $this->varToString($v));
}
return sprintf('Array(%s)', implode(', ', $a));
}
if (is_resource($var)) {
return sprintf('Resource(%s)', get_resource_type($var));
}
if (null === $var) {
return'null';
}
if (false === $var) {
return'false';
}
if (true === $var) {
return'true';
}
return (string) $var;
}
}
}
namespace Symfony\Component\HttpKernel\Event
{
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
class FilterResponseEvent extends KernelEvent
{
private $response;
public function __construct(HttpKernelInterface $kernel, Request $request, $requestType, Response $response)
{
parent::__construct($kernel, $request, $requestType);
$this->setResponse($response);
}
public function getResponse()
{
return $this->response;
}
public function setResponse(Response $response)
{
$this->response = $response;
}
}
}
namespace Symfony\Component\HttpKernel\Event
{
use Symfony\Component\HttpFoundation\Response;
class GetResponseEvent extends KernelEvent
{
private $response;
public function getResponse()
{
return $this->response;
}
public function setResponse(Response $response)
{
$this->response = $response;
$this->stopPropagation();
}
public function hasResponse()
{
return null !== $this->response;
}
}
}
namespace Symfony\Component\HttpKernel\Event
{
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
class GetResponseForControllerResultEvent extends GetResponseEvent
{
private $controllerResult;
public function __construct(HttpKernelInterface $kernel, Request $request, $requestType, $controllerResult)
{
parent::__construct($kernel, $request, $requestType);
$this->controllerResult = $controllerResult;
}
public function getControllerResult()
{
return $this->controllerResult;
}
public function setControllerResult($controllerResult)
{
$this->controllerResult = $controllerResult;
}
}
}
namespace Symfony\Component\HttpKernel\Event
{
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
class GetResponseForExceptionEvent extends GetResponseEvent
{
private $exception;
public function __construct(HttpKernelInterface $kernel, Request $request, $requestType, \Exception $e)
{
parent::__construct($kernel, $request, $requestType);
$this->setException($e);
}
public function getException()
{
return $this->exception;
}
public function setException(\Exception $exception)
{
$this->exception = $exception;
}
}
}
namespace Symfony\Component\HttpKernel
{
final class KernelEvents
{
const REQUEST ='kernel.request';
const EXCEPTION ='kernel.exception';
const VIEW ='kernel.view';
const CONTROLLER ='kernel.controller';
const RESPONSE ='kernel.response';
const TERMINATE ='kernel.terminate';
const FINISH_REQUEST ='kernel.finish_request';
}
}
namespace Symfony\Component\HttpKernel\Config
{
use Symfony\Component\Config\FileLocator as BaseFileLocator;
use Symfony\Component\HttpKernel\KernelInterface;
class FileLocator extends BaseFileLocator
{
private $kernel;
private $path;
public function __construct(KernelInterface $kernel, $path = null, array $paths = array())
{
$this->kernel = $kernel;
if (null !== $path) {
$this->path = $path;
$paths[] = $path;
}
parent::__construct($paths);
}
public function locate($file, $currentPath = null, $first = true)
{
if (isset($file[0]) &&'@'=== $file[0]) {
return $this->kernel->locateResource($file, $this->path, $first);
}
return parent::locate($file, $currentPath, $first);
}
}
}
namespace Symfony\Bundle\FrameworkBundle\Controller
{
use Symfony\Component\HttpKernel\KernelInterface;
class ControllerNameParser
{
protected $kernel;
public function __construct(KernelInterface $kernel)
{
$this->kernel = $kernel;
}
public function parse($controller)
{
$originalController = $controller;
if (3 !== count($parts = explode(':', $controller))) {
throw new \InvalidArgumentException(sprintf('The "%s" controller is not a valid "a:b:c" controller string.', $controller));
}
list($bundle, $controller, $action) = $parts;
$controller = str_replace('/','\\', $controller);
$bundles = array();
try {
$allBundles = $this->kernel->getBundle($bundle, false);
} catch (\InvalidArgumentException $e) {
$message = sprintf('The "%s" (from the _controller value "%s") does not exist or is not enabled in your kernel!',
$bundle,
$originalController
);
if ($alternative = $this->findAlternative($bundle)) {
$message .= sprintf(' Did you mean "%s:%s:%s"?', $alternative, $controller, $action);
}
throw new \InvalidArgumentException($message, 0, $e);
}
foreach ($allBundles as $b) {
$try = $b->getNamespace().'\\Controller\\'.$controller.'Controller';
if (class_exists($try)) {
return $try.'::'.$action.'Action';
}
$bundles[] = $b->getName();
$msg = sprintf('The _controller value "%s:%s:%s" maps to a "%s" class, but this class was not found. Create this class or check the spelling of the class and its namespace.', $bundle, $controller, $action, $try);
}
if (count($bundles) > 1) {
$msg = sprintf('Unable to find controller "%s:%s" in bundles %s.', $bundle, $controller, implode(', ', $bundles));
}
throw new \InvalidArgumentException($msg);
}
public function build($controller)
{
if (0 === preg_match('#^(.*?\\\\Controller\\\\(.+)Controller)::(.+)Action$#', $controller, $match)) {
throw new \InvalidArgumentException(sprintf('The "%s" controller is not a valid "class::method" string.', $controller));
}
$className = $match[1];
$controllerName = $match[2];
$actionName = $match[3];
foreach ($this->kernel->getBundles() as $name => $bundle) {
if (0 !== strpos($className, $bundle->getNamespace())) {
continue;
}
return sprintf('%s:%s:%s', $name, $controllerName, $actionName);
}
throw new \InvalidArgumentException(sprintf('Unable to find a bundle that defines controller "%s".', $controller));
}
private function findAlternative($nonExistentBundleName)
{
$bundleNames = array_map(function ($b) {
return $b->getName();
}, $this->kernel->getBundles());
$alternative = null;
$shortest = null;
foreach ($bundleNames as $bundleName) {
if (false !== strpos($bundleName, $nonExistentBundleName)) {
return $bundleName;
}
$lev = levenshtein($nonExistentBundleName, $bundleName);
if ($lev <= strlen($nonExistentBundleName) / 3 && ($alternative === null || $lev < $shortest)) {
$alternative = $bundleName;
}
}
return $alternative;
}
}
}
namespace Symfony\Bundle\FrameworkBundle\Controller
{
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Controller\ControllerResolver as BaseControllerResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
class ControllerResolver extends BaseControllerResolver
{
protected $container;
protected $parser;
public function __construct(ContainerInterface $container, ControllerNameParser $parser, LoggerInterface $logger = null)
{
$this->container = $container;
$this->parser = $parser;
parent::__construct($logger);
}
protected function createController($controller)
{
if (false === strpos($controller,'::')) {
$count = substr_count($controller,':');
if (2 == $count) {
$controller = $this->parser->parse($controller);
} elseif (1 == $count) {
list($service, $method) = explode(':', $controller, 2);
return array($this->container->get($service), $method);
} elseif ($this->container->has($controller) && method_exists($service = $this->container->get($controller),'__invoke')) {
return $service;
} else {
throw new \LogicException(sprintf('Unable to parse the controller name "%s".', $controller));
}
}
return parent::createController($controller);
}
protected function instantiateController($class)
{
$controller = parent::instantiateController($class);
if ($controller instanceof ContainerAwareInterface) {
$controller->setContainer($this->container);
}
return $controller;
}
}
}
namespace Symfony\Component\Security\Http
{
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
class Firewall implements EventSubscriberInterface
{
private $map;
private $dispatcher;
private $exceptionListeners;
public function __construct(FirewallMapInterface $map, EventDispatcherInterface $dispatcher)
{
$this->map = $map;
$this->dispatcher = $dispatcher;
$this->exceptionListeners = new \SplObjectStorage();
}
public function onKernelRequest(GetResponseEvent $event)
{
if (!$event->isMasterRequest()) {
return;
}
list($listeners, $exceptionListener) = $this->map->getListeners($event->getRequest());
if (null !== $exceptionListener) {
$this->exceptionListeners[$event->getRequest()] = $exceptionListener;
$exceptionListener->register($this->dispatcher);
}
foreach ($listeners as $listener) {
$listener->handle($event);
if ($event->hasResponse()) {
break;
}
}
}
public function onKernelFinishRequest(FinishRequestEvent $event)
{
$request = $event->getRequest();
if (isset($this->exceptionListeners[$request])) {
$this->exceptionListeners[$request]->unregister($this->dispatcher);
unset($this->exceptionListeners[$request]);
}
}
public static function getSubscribedEvents()
{
return array(
KernelEvents::REQUEST => array('onKernelRequest', 8),
KernelEvents::FINISH_REQUEST =>'onKernelFinishRequest',
);
}
}
}
namespace Symfony\Component\Security\Core\User
{
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
interface UserProviderInterface
{
public function loadUserByUsername($username);
public function refreshUser(UserInterface $user);
public function supportsClass($class);
}
}
namespace Symfony\Component\Security\Core\Authentication
{
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
interface AuthenticationManagerInterface
{
public function authenticate(TokenInterface $token);
}
}
namespace Symfony\Component\Security\Core\Authentication
{
use Symfony\Component\Security\Core\Event\AuthenticationFailureEvent;
use Symfony\Component\Security\Core\Event\AuthenticationEvent;
use Symfony\Component\Security\Core\AuthenticationEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\ProviderNotFoundException;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
class AuthenticationProviderManager implements AuthenticationManagerInterface
{
private $providers;
private $eraseCredentials;
private $eventDispatcher;
public function __construct(array $providers, $eraseCredentials = true)
{
if (!$providers) {
throw new \InvalidArgumentException('You must at least add one authentication provider.');
}
foreach ($providers as $provider) {
if (!$provider instanceof AuthenticationProviderInterface) {
throw new \InvalidArgumentException(sprintf('Provider "%s" must implement the AuthenticationProviderInterface.', get_class($provider)));
}
}
$this->providers = $providers;
$this->eraseCredentials = (bool) $eraseCredentials;
}
public function setEventDispatcher(EventDispatcherInterface $dispatcher)
{
$this->eventDispatcher = $dispatcher;
}
public function authenticate(TokenInterface $token)
{
$lastException = null;
$result = null;
foreach ($this->providers as $provider) {
if (!$provider->supports($token)) {
continue;
}
try {
$result = $provider->authenticate($token);
if (null !== $result) {
break;
}
} catch (AccountStatusException $e) {
$e->setToken($token);
throw $e;
} catch (AuthenticationException $e) {
$lastException = $e;
}
}
if (null !== $result) {
if (true === $this->eraseCredentials) {
$result->eraseCredentials();
}
if (null !== $this->eventDispatcher) {
$this->eventDispatcher->dispatch(AuthenticationEvents::AUTHENTICATION_SUCCESS, new AuthenticationEvent($result));
}
return $result;
}
if (null === $lastException) {
$lastException = new ProviderNotFoundException(sprintf('No Authentication Provider found for token of class "%s".', get_class($token)));
}
if (null !== $this->eventDispatcher) {
$this->eventDispatcher->dispatch(AuthenticationEvents::AUTHENTICATION_FAILURE, new AuthenticationFailureEvent($token, $lastException));
}
$lastException->setToken($token);
throw $lastException;
}
}
}
namespace Symfony\Component\Security\Core\Authentication\Token\Storage
{
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
interface TokenStorageInterface
{
public function getToken();
public function setToken(TokenInterface $token = null);
}
}
namespace Symfony\Component\Security\Core\Authentication\Token\Storage
{
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
class TokenStorage implements TokenStorageInterface
{
private $token;
public function getToken()
{
return $this->token;
}
public function setToken(TokenInterface $token = null)
{
$this->token = $token;
}
}
}
namespace Symfony\Component\Security\Core\Authorization
{
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
interface AccessDecisionManagerInterface
{
public function decide(TokenInterface $token, array $attributes, $object = null);
public function supportsAttribute($attribute);
public function supportsClass($class);
}
}
namespace Symfony\Component\Security\Core\Authorization
{
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
class AccessDecisionManager implements AccessDecisionManagerInterface
{
const STRATEGY_AFFIRMATIVE ='affirmative';
const STRATEGY_CONSENSUS ='consensus';
const STRATEGY_UNANIMOUS ='unanimous';
private $voters;
private $strategy;
private $allowIfAllAbstainDecisions;
private $allowIfEqualGrantedDeniedDecisions;
public function __construct(array $voters, $strategy = self::STRATEGY_AFFIRMATIVE, $allowIfAllAbstainDecisions = false, $allowIfEqualGrantedDeniedDecisions = true)
{
if (!$voters) {
throw new \InvalidArgumentException('You must at least add one voter.');
}
$strategyMethod ='decide'.ucfirst($strategy);
if (!is_callable(array($this, $strategyMethod))) {
throw new \InvalidArgumentException(sprintf('The strategy "%s" is not supported.', $strategy));
}
$this->voters = $voters;
$this->strategy = $strategyMethod;
$this->allowIfAllAbstainDecisions = (bool) $allowIfAllAbstainDecisions;
$this->allowIfEqualGrantedDeniedDecisions = (bool) $allowIfEqualGrantedDeniedDecisions;
}
public function decide(TokenInterface $token, array $attributes, $object = null)
{
return $this->{$this->strategy}($token, $attributes, $object);
}
public function supportsAttribute($attribute)
{
foreach ($this->voters as $voter) {
if ($voter->supportsAttribute($attribute)) {
return true;
}
}
return false;
}
public function supportsClass($class)
{
foreach ($this->voters as $voter) {
if ($voter->supportsClass($class)) {
return true;
}
}
return false;
}
private function decideAffirmative(TokenInterface $token, array $attributes, $object = null)
{
$deny = 0;
foreach ($this->voters as $voter) {
$result = $voter->vote($token, $object, $attributes);
switch ($result) {
case VoterInterface::ACCESS_GRANTED:
return true;
case VoterInterface::ACCESS_DENIED:
++$deny;
break;
default:
break;
}
}
if ($deny > 0) {
return false;
}
return $this->allowIfAllAbstainDecisions;
}
private function decideConsensus(TokenInterface $token, array $attributes, $object = null)
{
$grant = 0;
$deny = 0;
$abstain = 0;
foreach ($this->voters as $voter) {
$result = $voter->vote($token, $object, $attributes);
switch ($result) {
case VoterInterface::ACCESS_GRANTED:
++$grant;
break;
case VoterInterface::ACCESS_DENIED:
++$deny;
break;
default:
++$abstain;
break;
}
}
if ($grant > $deny) {
return true;
}
if ($deny > $grant) {
return false;
}
if ($grant == $deny && $grant != 0) {
return $this->allowIfEqualGrantedDeniedDecisions;
}
return $this->allowIfAllAbstainDecisions;
}
private function decideUnanimous(TokenInterface $token, array $attributes, $object = null)
{
$grant = 0;
foreach ($attributes as $attribute) {
foreach ($this->voters as $voter) {
$result = $voter->vote($token, $object, array($attribute));
switch ($result) {
case VoterInterface::ACCESS_GRANTED:
++$grant;
break;
case VoterInterface::ACCESS_DENIED:
return false;
default:
break;
}
}
}
if ($grant > 0) {
return true;
}
return $this->allowIfAllAbstainDecisions;
}
}
}
namespace Symfony\Component\Security\Core\Authorization
{
interface AuthorizationCheckerInterface
{
public function isGranted($attributes, $object = null);
}
}
namespace Symfony\Component\Security\Core\Authorization
{
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
class AuthorizationChecker implements AuthorizationCheckerInterface
{
private $tokenStorage;
private $accessDecisionManager;
private $authenticationManager;
private $alwaysAuthenticate;
public function __construct(TokenStorageInterface $tokenStorage, AuthenticationManagerInterface $authenticationManager, AccessDecisionManagerInterface $accessDecisionManager, $alwaysAuthenticate = false)
{
$this->tokenStorage = $tokenStorage;
$this->authenticationManager = $authenticationManager;
$this->accessDecisionManager = $accessDecisionManager;
$this->alwaysAuthenticate = $alwaysAuthenticate;
}
final public function isGranted($attributes, $object = null)
{
if (null === ($token = $this->tokenStorage->getToken())) {
throw new AuthenticationCredentialsNotFoundException('The token storage contains no authentication token. One possible reason may be that there is no firewall configured for this URL.');
}
if ($this->alwaysAuthenticate || !$token->isAuthenticated()) {
$this->tokenStorage->setToken($token = $this->authenticationManager->authenticate($token));
}
if (!is_array($attributes)) {
$attributes = array($attributes);
}
return $this->accessDecisionManager->decide($token, $attributes, $object);
}
}
}
namespace Symfony\Component\Security\Core\Authorization\Voter
{
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
interface VoterInterface
{
const ACCESS_GRANTED = 1;
const ACCESS_ABSTAIN = 0;
const ACCESS_DENIED = -1;
public function supportsAttribute($attribute);
public function supportsClass($class);
public function vote(TokenInterface $token, $object, array $attributes);
}
}
namespace Symfony\Component\Security\Http
{
use Symfony\Component\HttpFoundation\Request;
interface FirewallMapInterface
{
public function getListeners(Request $request);
}
}
namespace Symfony\Bundle\SecurityBundle\Security
{
use Symfony\Component\Security\Http\FirewallMapInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
class FirewallMap implements FirewallMapInterface
{
protected $container;
protected $map;
public function __construct(ContainerInterface $container, array $map)
{
$this->container = $container;
$this->map = $map;
}
public function getListeners(Request $request)
{
foreach ($this->map as $contextId => $requestMatcher) {
if (null === $requestMatcher || $requestMatcher->matches($request)) {
return $this->container->get($contextId)->getContext();
}
}
return array(array(), null);
}
}
}
namespace Symfony\Bundle\SecurityBundle\Security
{
use Symfony\Component\Security\Http\Firewall\ExceptionListener;
class FirewallContext
{
private $listeners;
private $exceptionListener;
public function __construct(array $listeners, ExceptionListener $exceptionListener = null)
{
$this->listeners = $listeners;
$this->exceptionListener = $exceptionListener;
}
public function getContext()
{
return array($this->listeners, $this->exceptionListener);
}
}
}
namespace Symfony\Component\HttpFoundation
{
interface RequestMatcherInterface
{
public function matches(Request $request);
}
}
namespace Symfony\Component\HttpFoundation
{
class RequestMatcher implements RequestMatcherInterface
{
private $path;
private $host;
private $methods = array();
private $ips = array();
private $attributes = array();
private $schemes = array();
public function __construct($path = null, $host = null, $methods = null, $ips = null, array $attributes = array(), $schemes = null)
{
$this->matchPath($path);
$this->matchHost($host);
$this->matchMethod($methods);
$this->matchIps($ips);
$this->matchScheme($schemes);
foreach ($attributes as $k => $v) {
$this->matchAttribute($k, $v);
}
}
public function matchScheme($scheme)
{
$this->schemes = array_map('strtolower', (array) $scheme);
}
public function matchHost($regexp)
{
$this->host = $regexp;
}
public function matchPath($regexp)
{
$this->path = $regexp;
}
public function matchIp($ip)
{
$this->matchIps($ip);
}
public function matchIps($ips)
{
$this->ips = (array) $ips;
}
public function matchMethod($method)
{
$this->methods = array_map('strtoupper', (array) $method);
}
public function matchAttribute($key, $regexp)
{
$this->attributes[$key] = $regexp;
}
public function matches(Request $request)
{
if ($this->schemes && !in_array($request->getScheme(), $this->schemes)) {
return false;
}
if ($this->methods && !in_array($request->getMethod(), $this->methods)) {
return false;
}
foreach ($this->attributes as $key => $pattern) {
if (!preg_match('{'.$pattern.'}', $request->attributes->get($key))) {
return false;
}
}
if (null !== $this->path && !preg_match('{'.$this->path.'}', rawurldecode($request->getPathInfo()))) {
return false;
}
if (null !== $this->host && !preg_match('{'.$this->host.'}i', $request->getHost())) {
return false;
}
if (IpUtils::checkIp($request->getClientIp(), $this->ips)) {
return true;
}
return count($this->ips) === 0;
}
}
}
namespace Monolog\Formatter
{
interface FormatterInterface
{
public function format(array $record);
public function formatBatch(array $records);
}
}
namespace Monolog\Formatter
{
use Exception;
class NormalizerFormatter implements FormatterInterface
{
const SIMPLE_DATE ="Y-m-d H:i:s";
protected $dateFormat;
public function __construct($dateFormat = null)
{
$this->dateFormat = $dateFormat ?: static::SIMPLE_DATE;
if (!function_exists('json_encode')) {
throw new \RuntimeException('PHP\'s json extension is required to use Monolog\'s NormalizerFormatter');
}
}
public function format(array $record)
{
return $this->normalize($record);
}
public function formatBatch(array $records)
{
foreach ($records as $key => $record) {
$records[$key] = $this->format($record);
}
return $records;
}
protected function normalize($data)
{
if (null === $data || is_scalar($data)) {
if (is_float($data)) {
if (is_infinite($data)) {
return ($data > 0 ?'':'-') .'INF';
}
if (is_nan($data)) {
return'NaN';
}
}
return $data;
}
if (is_array($data) || $data instanceof \Traversable) {
$normalized = array();
$count = 1;
foreach ($data as $key => $value) {
if ($count++ >= 1000) {
$normalized['...'] ='Over 1000 items, aborting normalization';
break;
}
$normalized[$key] = $this->normalize($value);
}
return $normalized;
}
if ($data instanceof \DateTime) {
return $data->format($this->dateFormat);
}
if (is_object($data)) {
if ($data instanceof Exception) {
return $this->normalizeException($data);
}
if (method_exists($data,'__toString') && !$data instanceof \JsonSerializable) {
$value = (string) $data;
} else {
$value = $this->toJson($data, true);
}
return sprintf("[object] (%s: %s)", get_class($data), $value);
}
if (is_resource($data)) {
return sprintf('[resource] (%s)', get_resource_type($data));
}
return'[unknown('.gettype($data).')]';
}
protected function normalizeException(Exception $e)
{
$data = array('class'=> get_class($e),'message'=> $e->getMessage(),'code'=> $e->getCode(),'file'=> $e->getFile().':'.$e->getLine(),
);
$trace = $e->getTrace();
foreach ($trace as $frame) {
if (isset($frame['file'])) {
$data['trace'][] = $frame['file'].':'.$frame['line'];
} else {
$data['trace'][] = $this->toJson($this->normalize($frame), true);
}
}
if ($previous = $e->getPrevious()) {
$data['previous'] = $this->normalizeException($previous);
}
return $data;
}
protected function toJson($data, $ignoreErrors = false)
{
if ($ignoreErrors) {
if (version_compare(PHP_VERSION,'5.4.0','>=')) {
return @json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
return @json_encode($data);
}
if (version_compare(PHP_VERSION,'5.4.0','>=')) {
$json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} else {
$json = json_encode($data);
}
if ($json === false) {
$this->throwEncodeError(json_last_error(), $data);
}
return $json;
}
private function throwEncodeError($code, $data)
{
switch ($code) {
case JSON_ERROR_DEPTH:
$msg ='Maximum stack depth exceeded';
break;
case JSON_ERROR_STATE_MISMATCH:
$msg ='Underflow or the modes mismatch';
break;
case JSON_ERROR_CTRL_CHAR:
$msg ='Unexpected control character found';
break;
case JSON_ERROR_UTF8:
$msg ='Malformed UTF-8 characters, possibly incorrectly encoded';
break;
default:
$msg ='Unknown error';
}
throw new \RuntimeException('JSON encoding failed: '.$msg.'. Encoding: '.var_export($data, true));
}
}
}
namespace Monolog\Formatter
{
use Exception;
class LineFormatter extends NormalizerFormatter
{
const SIMPLE_FORMAT ="[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";
protected $format;
protected $allowInlineLineBreaks;
protected $ignoreEmptyContextAndExtra;
protected $includeStacktraces;
public function __construct($format = null, $dateFormat = null, $allowInlineLineBreaks = false, $ignoreEmptyContextAndExtra = false)
{
$this->format = $format ?: static::SIMPLE_FORMAT;
$this->allowInlineLineBreaks = $allowInlineLineBreaks;
$this->ignoreEmptyContextAndExtra = $ignoreEmptyContextAndExtra;
parent::__construct($dateFormat);
}
public function includeStacktraces($include = true)
{
$this->includeStacktraces = $include;
if ($this->includeStacktraces) {
$this->allowInlineLineBreaks = true;
}
}
public function allowInlineLineBreaks($allow = true)
{
$this->allowInlineLineBreaks = $allow;
}
public function ignoreEmptyContextAndExtra($ignore = true)
{
$this->ignoreEmptyContextAndExtra = $ignore;
}
public function format(array $record)
{
$vars = parent::format($record);
$output = $this->format;
foreach ($vars['extra'] as $var => $val) {
if (false !== strpos($output,'%extra.'.$var.'%')) {
$output = str_replace('%extra.'.$var.'%', $this->stringify($val), $output);
unset($vars['extra'][$var]);
}
}
if ($this->ignoreEmptyContextAndExtra) {
if (empty($vars['context'])) {
unset($vars['context']);
$output = str_replace('%context%','', $output);
}
if (empty($vars['extra'])) {
unset($vars['extra']);
$output = str_replace('%extra%','', $output);
}
}
foreach ($vars as $var => $val) {
if (false !== strpos($output,'%'.$var.'%')) {
$output = str_replace('%'.$var.'%', $this->stringify($val), $output);
}
}
return $output;
}
public function formatBatch(array $records)
{
$message ='';
foreach ($records as $record) {
$message .= $this->format($record);
}
return $message;
}
public function stringify($value)
{
return $this->replaceNewlines($this->convertToString($value));
}
protected function normalizeException(Exception $e)
{
$previousText ='';
if ($previous = $e->getPrevious()) {
do {
$previousText .=', '.get_class($previous).'(code: '.$previous->getCode().'): '.$previous->getMessage().' at '.$previous->getFile().':'.$previous->getLine();
} while ($previous = $previous->getPrevious());
}
$str ='[object] ('.get_class($e).'(code: '.$e->getCode().'): '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine().$previousText.')';
if ($this->includeStacktraces) {
$str .="\n[stacktrace]\n".$e->getTraceAsString();
}
return $str;
}
protected function convertToString($data)
{
if (null === $data || is_bool($data)) {
return var_export($data, true);
}
if (is_scalar($data)) {
return (string) $data;
}
if (version_compare(PHP_VERSION,'5.4.0','>=')) {
return $this->toJson($data, true);
}
return str_replace('\\/','/', @json_encode($data));
}
protected function replaceNewlines($str)
{
if ($this->allowInlineLineBreaks) {
return $str;
}
return str_replace(array("\r\n","\r","\n"),' ', $str);
}
}
}
namespace Monolog\Handler
{
use Monolog\Formatter\FormatterInterface;
interface HandlerInterface
{
public function isHandling(array $record);
public function handle(array $record);
public function handleBatch(array $records);
public function pushProcessor($callback);
public function popProcessor();
public function setFormatter(FormatterInterface $formatter);
public function getFormatter();
}
}
namespace Monolog\Handler
{
use Monolog\Logger;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
abstract class AbstractHandler implements HandlerInterface
{
protected $level = Logger::DEBUG;
protected $bubble = true;
protected $formatter;
protected $processors = array();
public function __construct($level = Logger::DEBUG, $bubble = true)
{
$this->setLevel($level);
$this->bubble = $bubble;
}
public function isHandling(array $record)
{
return $record['level'] >= $this->level;
}
public function handleBatch(array $records)
{
foreach ($records as $record) {
$this->handle($record);
}
}
public function close()
{
}
public function pushProcessor($callback)
{
if (!is_callable($callback)) {
throw new \InvalidArgumentException('Processors must be valid callables (callback or object with an __invoke method), '.var_export($callback, true).' given');
}
array_unshift($this->processors, $callback);
return $this;
}
public function popProcessor()
{
if (!$this->processors) {
throw new \LogicException('You tried to pop from an empty processor stack.');
}
return array_shift($this->processors);
}
public function setFormatter(FormatterInterface $formatter)
{
$this->formatter = $formatter;
return $this;
}
public function getFormatter()
{
if (!$this->formatter) {
$this->formatter = $this->getDefaultFormatter();
}
return $this->formatter;
}
public function setLevel($level)
{
$this->level = Logger::toMonologLevel($level);
return $this;
}
public function getLevel()
{
return $this->level;
}
public function setBubble($bubble)
{
$this->bubble = $bubble;
return $this;
}
public function getBubble()
{
return $this->bubble;
}
public function __destruct()
{
try {
$this->close();
} catch (\Exception $e) {
}
}
protected function getDefaultFormatter()
{
return new LineFormatter();
}
}
}
namespace Monolog\Handler
{
abstract class AbstractProcessingHandler extends AbstractHandler
{
public function handle(array $record)
{
if (!$this->isHandling($record)) {
return false;
}
$record = $this->processRecord($record);
$record['formatted'] = $this->getFormatter()->format($record);
$this->write($record);
return false === $this->bubble;
}
abstract protected function write(array $record);
protected function processRecord(array $record)
{
if ($this->processors) {
foreach ($this->processors as $processor) {
$record = call_user_func($processor, $record);
}
}
return $record;
}
}
}
namespace Monolog\Handler
{
use Monolog\Logger;
class StreamHandler extends AbstractProcessingHandler
{
protected $stream;
protected $url;
private $errorMessage;
protected $filePermission;
protected $useLocking;
private $dirCreated;
public function __construct($stream, $level = Logger::DEBUG, $bubble = true, $filePermission = null, $useLocking = false)
{
parent::__construct($level, $bubble);
if (is_resource($stream)) {
$this->stream = $stream;
} elseif (is_string($stream)) {
$this->url = $stream;
} else {
throw new \InvalidArgumentException('A stream must either be a resource or a string.');
}
$this->filePermission = $filePermission;
$this->useLocking = $useLocking;
}
public function close()
{
if (is_resource($this->stream)) {
fclose($this->stream);
}
$this->stream = null;
}
protected function write(array $record)
{
if (!is_resource($this->stream)) {
if (!$this->url) {
throw new \LogicException('Missing stream url, the stream can not be opened. This may be caused by a premature call to close().');
}
$this->createDir();
$this->errorMessage = null;
set_error_handler(array($this,'customErrorHandler'));
$this->stream = fopen($this->url,'a');
if ($this->filePermission !== null) {
@chmod($this->url, $this->filePermission);
}
restore_error_handler();
if (!is_resource($this->stream)) {
$this->stream = null;
throw new \UnexpectedValueException(sprintf('The stream or file "%s" could not be opened: '.$this->errorMessage, $this->url));
}
}
if ($this->useLocking) {
flock($this->stream, LOCK_EX);
}
fwrite($this->stream, (string) $record['formatted']);
if ($this->useLocking) {
flock($this->stream, LOCK_UN);
}
}
private function customErrorHandler($code, $msg)
{
$this->errorMessage = preg_replace('{^(fopen|mkdir)\(.*?\): }','', $msg);
}
private function getDirFromStream($stream)
{
$pos = strpos($stream,'://');
if ($pos === false) {
return dirname($stream);
}
if ('file://'=== substr($stream, 0, 7)) {
return dirname(substr($stream, 7));
}
return;
}
private function createDir()
{
if ($this->dirCreated) {
return;
}
$dir = $this->getDirFromStream($this->url);
if (null !== $dir && !is_dir($dir)) {
$this->errorMessage = null;
set_error_handler(array($this,'customErrorHandler'));
$status = mkdir($dir, 0777, true);
restore_error_handler();
if (false === $status) {
throw new \UnexpectedValueException(sprintf('There is no existing directory at "%s" and its not buildable: '.$this->errorMessage, $dir));
}
}
$this->dirCreated = true;
}
}
}
namespace Monolog\Handler
{
use Monolog\Handler\FingersCrossed\ErrorLevelActivationStrategy;
use Monolog\Handler\FingersCrossed\ActivationStrategyInterface;
use Monolog\Logger;
class FingersCrossedHandler extends AbstractHandler
{
protected $handler;
protected $activationStrategy;
protected $buffering = true;
protected $bufferSize;
protected $buffer = array();
protected $stopBuffering;
protected $passthruLevel;
public function __construct($handler, $activationStrategy = null, $bufferSize = 0, $bubble = true, $stopBuffering = true, $passthruLevel = null)
{
if (null === $activationStrategy) {
$activationStrategy = new ErrorLevelActivationStrategy(Logger::WARNING);
}
if (!$activationStrategy instanceof ActivationStrategyInterface) {
$activationStrategy = new ErrorLevelActivationStrategy($activationStrategy);
}
$this->handler = $handler;
$this->activationStrategy = $activationStrategy;
$this->bufferSize = $bufferSize;
$this->bubble = $bubble;
$this->stopBuffering = $stopBuffering;
if ($passthruLevel !== null) {
$this->passthruLevel = Logger::toMonologLevel($passthruLevel);
}
if (!$this->handler instanceof HandlerInterface && !is_callable($this->handler)) {
throw new \RuntimeException("The given handler (".json_encode($this->handler).") is not a callable nor a Monolog\Handler\HandlerInterface object");
}
}
public function isHandling(array $record)
{
return true;
}
public function handle(array $record)
{
if ($this->processors) {
foreach ($this->processors as $processor) {
$record = call_user_func($processor, $record);
}
}
if ($this->buffering) {
$this->buffer[] = $record;
if ($this->bufferSize > 0 && count($this->buffer) > $this->bufferSize) {
array_shift($this->buffer);
}
if ($this->activationStrategy->isHandlerActivated($record)) {
if ($this->stopBuffering) {
$this->buffering = false;
}
if (!$this->handler instanceof HandlerInterface) {
$this->handler = call_user_func($this->handler, $record, $this);
if (!$this->handler instanceof HandlerInterface) {
throw new \RuntimeException("The factory callable should return a HandlerInterface");
}
}
$this->handler->handleBatch($this->buffer);
$this->buffer = array();
}
} else {
$this->handler->handle($record);
}
return false === $this->bubble;
}
public function close()
{
if (null !== $this->passthruLevel) {
$level = $this->passthruLevel;
$this->buffer = array_filter($this->buffer, function ($record) use ($level) {
return $record['level'] >= $level;
});
if (count($this->buffer) > 0) {
$this->handler->handleBatch($this->buffer);
$this->buffer = array();
}
}
}
public function reset()
{
$this->buffering = true;
}
public function clear()
{
$this->buffer = array();
$this->reset();
}
}
}
namespace Monolog\Handler
{
use Monolog\Logger;
class FilterHandler extends AbstractHandler
{
protected $handler;
protected $acceptedLevels;
protected $bubble;
public function __construct($handler, $minLevelOrList = Logger::DEBUG, $maxLevel = Logger::EMERGENCY, $bubble = true)
{
$this->handler = $handler;
$this->bubble = $bubble;
$this->setAcceptedLevels($minLevelOrList, $maxLevel);
if (!$this->handler instanceof HandlerInterface && !is_callable($this->handler)) {
throw new \RuntimeException("The given handler (".json_encode($this->handler).") is not a callable nor a Monolog\Handler\HandlerInterface object");
}
}
public function getAcceptedLevels()
{
return array_flip($this->acceptedLevels);
}
public function setAcceptedLevels($minLevelOrList = Logger::DEBUG, $maxLevel = Logger::EMERGENCY)
{
if (is_array($minLevelOrList)) {
$acceptedLevels = array_map('Monolog\Logger::toMonologLevel', $minLevelOrList);
} else {
$minLevelOrList = Logger::toMonologLevel($minLevelOrList);
$maxLevel = Logger::toMonologLevel($maxLevel);
$acceptedLevels = array_values(array_filter(Logger::getLevels(), function ($level) use ($minLevelOrList, $maxLevel) {
return $level >= $minLevelOrList && $level <= $maxLevel;
}));
}
$this->acceptedLevels = array_flip($acceptedLevels);
}
public function isHandling(array $record)
{
return isset($this->acceptedLevels[$record['level']]);
}
public function handle(array $record)
{
if (!$this->isHandling($record)) {
return false;
}
if (!$this->handler instanceof HandlerInterface) {
$this->handler = call_user_func($this->handler, $record, $this);
if (!$this->handler instanceof HandlerInterface) {
throw new \RuntimeException("The factory callable should return a HandlerInterface");
}
}
if ($this->processors) {
foreach ($this->processors as $processor) {
$record = call_user_func($processor, $record);
}
}
$this->handler->handle($record);
return false === $this->bubble;
}
public function handleBatch(array $records)
{
$filtered = array();
foreach ($records as $record) {
if ($this->isHandling($record)) {
$filtered[] = $record;
}
}
$this->handler->handleBatch($filtered);
}
}
}
namespace Monolog\Handler
{
class TestHandler extends AbstractProcessingHandler
{
protected $records = array();
protected $recordsByLevel = array();
public function getRecords()
{
return $this->records;
}
protected function hasRecordRecords($level)
{
return isset($this->recordsByLevel[$level]);
}
protected function hasRecord($record, $level)
{
if (is_array($record)) {
$record = $record['message'];
}
return $this->hasRecordThatPasses(function ($rec) use ($record) {
return $rec['message'] === $record;
}, $level);
}
public function hasRecordThatContains($message, $level)
{
return $this->hasRecordThatPasses(function ($rec) use ($message) {
return strpos($rec['message'], $message) !== false;
}, $level);
}
public function hasRecordThatMatches($regex, $level)
{
return $this->hasRecordThatPasses(function ($rec) use ($regex) {
return preg_match($regex, $rec['message']) > 0;
}, $level);
}
public function hasRecordThatPasses($predicate, $level)
{
if (!is_callable($predicate)) {
throw new \InvalidArgumentException("Expected a callable for hasRecordThatSucceeds");
}
if (!isset($this->recordsByLevel[$level])) {
return false;
}
foreach ($this->recordsByLevel[$level] as $i => $rec) {
if (call_user_func($predicate, $rec, $i)) {
return true;
}
}
return false;
}
protected function write(array $record)
{
$this->recordsByLevel[$record['level']][] = $record;
$this->records[] = $record;
}
public function __call($method, $args)
{
if (preg_match('/(.*)(Debug|Info|Notice|Warning|Error|Critical|Alert|Emergency)(.*)/', $method, $matches) > 0) {
$genericMethod = $matches[1] .'Record'. $matches[3];
$level = constant('Monolog\Logger::'. strtoupper($matches[2]));
if (method_exists($this, $genericMethod)) {
$args[] = $level;
return call_user_func_array(array($this, $genericMethod), $args);
}
}
throw new \BadMethodCallException('Call to undefined method '. get_class($this) .'::'. $method .'()');
}
}
}
namespace Psr\Log
{
interface LoggerInterface
{
public function emergency($message, array $context = array());
public function alert($message, array $context = array());
public function critical($message, array $context = array());
public function error($message, array $context = array());
public function warning($message, array $context = array());
public function notice($message, array $context = array());
public function info($message, array $context = array());
public function debug($message, array $context = array());
public function log($level, $message, array $context = array());
}
}
namespace Monolog
{
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\StreamHandler;
use Psr\Log\LoggerInterface;
use Psr\Log\InvalidArgumentException;
class Logger implements LoggerInterface
{
const DEBUG = 100;
const INFO = 200;
const NOTICE = 250;
const WARNING = 300;
const ERROR = 400;
const CRITICAL = 500;
const ALERT = 550;
const EMERGENCY = 600;
const API = 1;
protected static $levels = array(
100 =>'DEBUG',
200 =>'INFO',
250 =>'NOTICE',
300 =>'WARNING',
400 =>'ERROR',
500 =>'CRITICAL',
550 =>'ALERT',
600 =>'EMERGENCY',
);
protected static $timezone;
protected $name;
protected $handlers;
protected $processors;
public function __construct($name, array $handlers = array(), array $processors = array())
{
$this->name = $name;
$this->handlers = $handlers;
$this->processors = $processors;
}
public function getName()
{
return $this->name;
}
public function pushHandler(HandlerInterface $handler)
{
array_unshift($this->handlers, $handler);
return $this;
}
public function popHandler()
{
if (!$this->handlers) {
throw new \LogicException('You tried to pop from an empty handler stack.');
}
return array_shift($this->handlers);
}
public function setHandlers(array $handlers)
{
$this->handlers = array();
foreach (array_reverse($handlers) as $handler) {
$this->pushHandler($handler);
}
return $this;
}
public function getHandlers()
{
return $this->handlers;
}
public function pushProcessor($callback)
{
if (!is_callable($callback)) {
throw new \InvalidArgumentException('Processors must be valid callables (callback or object with an __invoke method), '.var_export($callback, true).' given');
}
array_unshift($this->processors, $callback);
return $this;
}
public function popProcessor()
{
if (!$this->processors) {
throw new \LogicException('You tried to pop from an empty processor stack.');
}
return array_shift($this->processors);
}
public function getProcessors()
{
return $this->processors;
}
public function addRecord($level, $message, array $context = array())
{
if (!$this->handlers) {
$this->pushHandler(new StreamHandler('php://stderr', static::DEBUG));
}
$levelName = static::getLevelName($level);
$handlerKey = null;
foreach ($this->handlers as $key => $handler) {
if ($handler->isHandling(array('level'=> $level))) {
$handlerKey = $key;
break;
}
}
if (null === $handlerKey) {
return false;
}
if (!static::$timezone) {
static::$timezone = new \DateTimeZone(date_default_timezone_get() ?:'UTC');
}
$record = array('message'=> (string) $message,'context'=> $context,'level'=> $level,'level_name'=> $levelName,'channel'=> $this->name,'datetime'=> \DateTime::createFromFormat('U.u', sprintf('%.6F', microtime(true)), static::$timezone)->setTimezone(static::$timezone),'extra'=> array(),
);
foreach ($this->processors as $processor) {
$record = call_user_func($processor, $record);
}
while (isset($this->handlers[$handlerKey]) &&
false === $this->handlers[$handlerKey]->handle($record)) {
$handlerKey++;
}
return true;
}
public function addDebug($message, array $context = array())
{
return $this->addRecord(static::DEBUG, $message, $context);
}
public function addInfo($message, array $context = array())
{
return $this->addRecord(static::INFO, $message, $context);
}
public function addNotice($message, array $context = array())
{
return $this->addRecord(static::NOTICE, $message, $context);
}
public function addWarning($message, array $context = array())
{
return $this->addRecord(static::WARNING, $message, $context);
}
public function addError($message, array $context = array())
{
return $this->addRecord(static::ERROR, $message, $context);
}
public function addCritical($message, array $context = array())
{
return $this->addRecord(static::CRITICAL, $message, $context);
}
public function addAlert($message, array $context = array())
{
return $this->addRecord(static::ALERT, $message, $context);
}
public function addEmergency($message, array $context = array())
{
return $this->addRecord(static::EMERGENCY, $message, $context);
}
public static function getLevels()
{
return array_flip(static::$levels);
}
public static function getLevelName($level)
{
if (!isset(static::$levels[$level])) {
throw new InvalidArgumentException('Level "'.$level.'" is not defined, use one of: '.implode(', ', array_keys(static::$levels)));
}
return static::$levels[$level];
}
public static function toMonologLevel($level)
{
if (is_string($level) && defined(__CLASS__.'::'.strtoupper($level))) {
return constant(__CLASS__.'::'.strtoupper($level));
}
return $level;
}
public function isHandling($level)
{
$record = array('level'=> $level,
);
foreach ($this->handlers as $handler) {
if ($handler->isHandling($record)) {
return true;
}
}
return false;
}
public function log($level, $message, array $context = array())
{
$level = static::toMonologLevel($level);
return $this->addRecord($level, $message, $context);
}
public function debug($message, array $context = array())
{
return $this->addRecord(static::DEBUG, $message, $context);
}
public function info($message, array $context = array())
{
return $this->addRecord(static::INFO, $message, $context);
}
public function notice($message, array $context = array())
{
return $this->addRecord(static::NOTICE, $message, $context);
}
public function warn($message, array $context = array())
{
return $this->addRecord(static::WARNING, $message, $context);
}
public function warning($message, array $context = array())
{
return $this->addRecord(static::WARNING, $message, $context);
}
public function err($message, array $context = array())
{
return $this->addRecord(static::ERROR, $message, $context);
}
public function error($message, array $context = array())
{
return $this->addRecord(static::ERROR, $message, $context);
}
public function crit($message, array $context = array())
{
return $this->addRecord(static::CRITICAL, $message, $context);
}
public function critical($message, array $context = array())
{
return $this->addRecord(static::CRITICAL, $message, $context);
}
public function alert($message, array $context = array())
{
return $this->addRecord(static::ALERT, $message, $context);
}
public function emerg($message, array $context = array())
{
return $this->addRecord(static::EMERGENCY, $message, $context);
}
public function emergency($message, array $context = array())
{
return $this->addRecord(static::EMERGENCY, $message, $context);
}
public static function setTimezone(\DateTimeZone $tz)
{
self::$timezone = $tz;
}
}
}
namespace Symfony\Component\HttpKernel\Log
{
use Psr\Log\LoggerInterface as PsrLogger;
interface LoggerInterface extends PsrLogger
{
public function emerg($message, array $context = array());
public function crit($message, array $context = array());
public function err($message, array $context = array());
public function warn($message, array $context = array());
}
}
namespace Symfony\Component\HttpKernel\Log
{
interface DebugLoggerInterface
{
public function getLogs();
public function countErrors();
}
}
namespace Symfony\Bridge\Monolog
{
use Monolog\Logger as BaseLogger;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;
class Logger extends BaseLogger implements LoggerInterface, DebugLoggerInterface
{
public function emerg($message, array $context = array())
{
@trigger_error('The '.__METHOD__.' method inherited from the Symfony\Component\HttpKernel\Log\LoggerInterface interface is deprecated since version 2.2 and will be removed in 3.0. Use the emergency() method instead, which is PSR-3 compatible.', E_USER_DEPRECATED);
return parent::addRecord(BaseLogger::EMERGENCY, $message, $context);
}
public function crit($message, array $context = array())
{
@trigger_error('The '.__METHOD__.' method inherited from the Symfony\Component\HttpKernel\Log\LoggerInterface interface is deprecated since version 2.2 and will be removed in 3.0. Use the method critical() method instead, which is PSR-3 compatible.', E_USER_DEPRECATED);
return parent::addRecord(BaseLogger::CRITICAL, $message, $context);
}
public function err($message, array $context = array())
{
@trigger_error('The '.__METHOD__.' method inherited from the Symfony\Component\HttpKernel\Log\LoggerInterface interface is deprecated since version 2.2 and will be removed in 3.0. Use the error() method instead, which is PSR-3 compatible.', E_USER_DEPRECATED);
return parent::addRecord(BaseLogger::ERROR, $message, $context);
}
public function warn($message, array $context = array())
{
@trigger_error('The '.__METHOD__.' method inherited from the Symfony\Component\HttpKernel\Log\LoggerInterface interface is deprecated since version 2.2 and will be removed in 3.0. Use the warning() method instead, which is PSR-3 compatible.', E_USER_DEPRECATED);
return parent::addRecord(BaseLogger::WARNING, $message, $context);
}
public function getLogs()
{
if ($logger = $this->getDebugLogger()) {
return $logger->getLogs();
}
return array();
}
public function countErrors()
{
if ($logger = $this->getDebugLogger()) {
return $logger->countErrors();
}
return 0;
}
private function getDebugLogger()
{
foreach ($this->handlers as $handler) {
if ($handler instanceof DebugLoggerInterface) {
return $handler;
}
}
}
}
}
namespace Symfony\Bridge\Monolog\Handler
{
use Monolog\Logger;
use Monolog\Handler\TestHandler;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;
class DebugHandler extends TestHandler implements DebugLoggerInterface
{
public function getLogs()
{
$records = array();
foreach ($this->records as $record) {
$records[] = array('timestamp'=> $record['datetime']->getTimestamp(),'message'=> $record['message'],'priority'=> $record['level'],'priorityName'=> $record['level_name'],'context'=> $record['context'],
);
}
return $records;
}
public function countErrors()
{
$cnt = 0;
$levels = array(Logger::ERROR, Logger::CRITICAL, Logger::ALERT, Logger::EMERGENCY);
foreach ($levels as $level) {
if (isset($this->recordsByLevel[$level])) {
$cnt += count($this->recordsByLevel[$level]);
}
}
return $cnt;
}
}
}
namespace Monolog\Handler\FingersCrossed
{
interface ActivationStrategyInterface
{
public function isHandlerActivated(array $record);
}
}
namespace Monolog\Handler\FingersCrossed
{
use Monolog\Logger;
class ErrorLevelActivationStrategy implements ActivationStrategyInterface
{
private $actionLevel;
public function __construct($actionLevel)
{
$this->actionLevel = Logger::toMonologLevel($actionLevel);
}
public function isHandlerActivated(array $record)
{
return $record['level'] >= $this->actionLevel;
}
}
}
namespace Assetic
{
interface ValueSupplierInterface
{
public function getValues();
}
}
namespace Symfony\Bundle\AsseticBundle
{
use Assetic\ValueSupplierInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
class DefaultValueSupplier implements ValueSupplierInterface
{
protected $container;
public function __construct(ContainerInterface $container)
{
$this->container = $container;
}
public function getValues()
{
if (!$this->container->isScopeActive('request')) {
return array();
}
$request = $this->container->get('request');
return array('locale'=> $request->getLocale(),'env'=> $this->container->getParameter('kernel.environment'),
);
}
}
}
namespace Assetic\Factory
{
use Assetic\Asset\AssetCollection;
use Assetic\Asset\AssetCollectionInterface;
use Assetic\Asset\AssetInterface;
use Assetic\Asset\AssetReference;
use Assetic\Asset\FileAsset;
use Assetic\Asset\GlobAsset;
use Assetic\Asset\HttpAsset;
use Assetic\AssetManager;
use Assetic\Factory\Worker\WorkerInterface;
use Assetic\Filter\DependencyExtractorInterface;
use Assetic\FilterManager;
class AssetFactory
{
private $root;
private $debug;
private $output;
private $workers;
private $am;
private $fm;
public function __construct($root, $debug = false)
{
$this->root = rtrim($root,'/');
$this->debug = $debug;
$this->output ='assetic/*';
$this->workers = array();
}
public function setDebug($debug)
{
$this->debug = $debug;
}
public function isDebug()
{
return $this->debug;
}
public function setDefaultOutput($output)
{
$this->output = $output;
}
public function addWorker(WorkerInterface $worker)
{
$this->workers[] = $worker;
}
public function getAssetManager()
{
return $this->am;
}
public function setAssetManager(AssetManager $am)
{
$this->am = $am;
}
public function getFilterManager()
{
return $this->fm;
}
public function setFilterManager(FilterManager $fm)
{
$this->fm = $fm;
}
public function createAsset($inputs = array(), $filters = array(), array $options = array())
{
if (!is_array($inputs)) {
$inputs = array($inputs);
}
if (!is_array($filters)) {
$filters = array($filters);
}
if (!isset($options['output'])) {
$options['output'] = $this->output;
}
if (!isset($options['vars'])) {
$options['vars'] = array();
}
if (!isset($options['debug'])) {
$options['debug'] = $this->debug;
}
if (!isset($options['root'])) {
$options['root'] = array($this->root);
} else {
if (!is_array($options['root'])) {
$options['root'] = array($options['root']);
}
$options['root'][] = $this->root;
}
if (!isset($options['name'])) {
$options['name'] = $this->generateAssetName($inputs, $filters, $options);
}
$asset = $this->createAssetCollection(array(), $options);
$extensions = array();
foreach ($inputs as $input) {
if (is_array($input)) {
$asset->add(call_user_func_array(array($this,'createAsset'), $input));
} else {
$asset->add($this->parseInput($input, $options));
$extensions[pathinfo($input, PATHINFO_EXTENSION)] = true;
}
}
foreach ($filters as $filter) {
if ('?'!= $filter[0]) {
$asset->ensureFilter($this->getFilter($filter));
} elseif (!$options['debug']) {
$asset->ensureFilter($this->getFilter(substr($filter, 1)));
}
}
if (!empty($options['vars'])) {
$toAdd = array();
foreach ($options['vars'] as $var) {
if (false !== strpos($options['output'],'{'.$var.'}')) {
continue;
}
$toAdd[] ='{'.$var.'}';
}
if ($toAdd) {
$options['output'] = str_replace('*','*.'.implode('.', $toAdd), $options['output']);
}
}
if (1 == count($extensions) && !pathinfo($options['output'], PATHINFO_EXTENSION) && $extension = key($extensions)) {
$options['output'] .='.'.$extension;
}
$asset->setTargetPath(str_replace('*', $options['name'], $options['output']));
return $this->applyWorkers($asset);
}
public function generateAssetName($inputs, $filters, $options = array())
{
foreach (array_diff(array_keys($options), array('output','debug','root')) as $key) {
unset($options[$key]);
}
ksort($options);
return substr(sha1(serialize($inputs).serialize($filters).serialize($options)), 0, 7);
}
public function getLastModified(AssetInterface $asset)
{
$mtime = 0;
foreach ($asset instanceof AssetCollectionInterface ? $asset : array($asset) as $leaf) {
$mtime = max($mtime, $leaf->getLastModified());
if (!$filters = $leaf->getFilters()) {
continue;
}
$prevFilters = array();
foreach ($filters as $filter) {
$prevFilters[] = $filter;
if (!$filter instanceof DependencyExtractorInterface) {
continue;
}
$clone = clone $leaf;
$clone->clearFilters();
foreach (array_slice($prevFilters, 0, -1) as $prevFilter) {
$clone->ensureFilter($prevFilter);
}
$clone->load();
foreach ($filter->getChildren($this, $clone->getContent(), $clone->getSourceDirectory()) as $child) {
$mtime = max($mtime, $this->getLastModified($child));
}
}
}
return $mtime;
}
protected function parseInput($input, array $options = array())
{
if ('@'== $input[0]) {
return $this->createAssetReference(substr($input, 1));
}
if (false !== strpos($input,'://') || 0 === strpos($input,'//')) {
return $this->createHttpAsset($input, $options['vars']);
}
if (self::isAbsolutePath($input)) {
if ($root = self::findRootDir($input, $options['root'])) {
$path = ltrim(substr($input, strlen($root)),'/');
} else {
$path = null;
}
} else {
$root = $this->root;
$path = $input;
$input = $this->root.'/'.$path;
}
if (false !== strpos($input,'*')) {
return $this->createGlobAsset($input, $root, $options['vars']);
}
return $this->createFileAsset($input, $root, $path, $options['vars']);
}
protected function createAssetCollection(array $assets = array(), array $options = array())
{
return new AssetCollection($assets, array(), null, isset($options['vars']) ? $options['vars'] : array());
}
protected function createAssetReference($name)
{
if (!$this->am) {
throw new \LogicException('There is no asset manager.');
}
return new AssetReference($this->am, $name);
}
protected function createHttpAsset($sourceUrl, $vars)
{
return new HttpAsset($sourceUrl, array(), false, $vars);
}
protected function createGlobAsset($glob, $root = null, $vars)
{
return new GlobAsset($glob, array(), $root, $vars);
}
protected function createFileAsset($source, $root = null, $path = null, $vars)
{
return new FileAsset($source, array(), $root, $path, $vars);
}
protected function getFilter($name)
{
if (!$this->fm) {
throw new \LogicException('There is no filter manager.');
}
return $this->fm->get($name);
}
private function applyWorkers(AssetCollectionInterface $asset)
{
foreach ($asset as $leaf) {
foreach ($this->workers as $worker) {
$retval = $worker->process($leaf, $this);
if ($retval instanceof AssetInterface && $leaf !== $retval) {
$asset->replaceLeaf($leaf, $retval);
}
}
}
foreach ($this->workers as $worker) {
$retval = $worker->process($asset, $this);
if ($retval instanceof AssetInterface) {
$asset = $retval;
}
}
return $asset instanceof AssetCollectionInterface ? $asset : $this->createAssetCollection(array($asset));
}
private static function isAbsolutePath($path)
{
return'/'== $path[0] ||'\\'== $path[0] || (3 < strlen($path) && ctype_alpha($path[0]) && $path[1] ==':'&& ('\\'== $path[2] ||'/'== $path[2]));
}
private static function findRootDir($path, array $roots)
{
foreach ($roots as $root) {
if (0 === strpos($path, $root)) {
return $root;
}
}
}
}
}
namespace Symfony\Bundle\AsseticBundle\Factory
{
use Assetic\Factory\AssetFactory as BaseAssetFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
class AssetFactory extends BaseAssetFactory
{
private $kernel;
private $container;
private $parameterBag;
public function __construct(KernelInterface $kernel, ContainerInterface $container, ParameterBagInterface $parameterBag, $baseDir, $debug = false)
{
$this->kernel = $kernel;
$this->container = $container;
$this->parameterBag = $parameterBag;
parent::__construct($baseDir, $debug);
}
protected function parseInput($input, array $options = array())
{
$input = $this->parameterBag->resolveValue($input);
if ('@'== $input[0] && false !== strpos($input,'/')) {
$bundle = substr($input, 1);
if (false !== $pos = strpos($bundle,'/')) {
$bundle = substr($bundle, 0, $pos);
}
$options['root'] = array($this->kernel->getBundle($bundle)->getPath());
if (false !== $pos = strpos($input,'*')) {
list($before, $after) = explode('*', $input, 2);
$input = $this->kernel->locateResource($before).'*'.$after;
} else {
$input = $this->kernel->locateResource($input);
}
}
return parent::parseInput($input, $options);
}
protected function createAssetReference($name)
{
if (!$this->getAssetManager()) {
$this->setAssetManager($this->container->get('assetic.asset_manager'));
}
return parent::createAssetReference($name);
}
protected function getFilter($name)
{
if (!$this->getFilterManager()) {
$this->setFilterManager($this->container->get('assetic.filter_manager'));
}
return parent::getFilter($name);
}
}
}
namespace Sensio\Bundle\FrameworkExtraBundle\EventListener
{
use Doctrine\Common\Annotations\Reader;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ConfigurationInterface;
use Doctrine\Common\Util\ClassUtils;
class ControllerListener implements EventSubscriberInterface
{
protected $reader;
public function __construct(Reader $reader)
{
$this->reader = $reader;
}
public function onKernelController(FilterControllerEvent $event)
{
if (!is_array($controller = $event->getController())) {
return;
}
$className = class_exists('Doctrine\Common\Util\ClassUtils') ? ClassUtils::getClass($controller[0]) : get_class($controller[0]);
$object = new \ReflectionClass($className);
$method = $object->getMethod($controller[1]);
$classConfigurations = $this->getConfigurations($this->reader->getClassAnnotations($object));
$methodConfigurations = $this->getConfigurations($this->reader->getMethodAnnotations($method));
$configurations = array();
foreach (array_merge(array_keys($classConfigurations), array_keys($methodConfigurations)) as $key) {
if (!array_key_exists($key, $classConfigurations)) {
$configurations[$key] = $methodConfigurations[$key];
} elseif (!array_key_exists($key, $methodConfigurations)) {
$configurations[$key] = $classConfigurations[$key];
} else {
if (is_array($classConfigurations[$key])) {
if (!is_array($methodConfigurations[$key])) {
throw new \UnexpectedValueException('Configurations should both be an array or both not be an array');
}
$configurations[$key] = array_merge($classConfigurations[$key], $methodConfigurations[$key]);
} else {
$configurations[$key] = $methodConfigurations[$key];
}
}
}
$request = $event->getRequest();
foreach ($configurations as $key => $attributes) {
$request->attributes->set($key, $attributes);
}
}
protected function getConfigurations(array $annotations)
{
$configurations = array();
foreach ($annotations as $configuration) {
if ($configuration instanceof ConfigurationInterface) {
if ($configuration->allowArray()) {
$configurations['_'.$configuration->getAliasName()][] = $configuration;
} elseif (!isset($configurations['_'.$configuration->getAliasName()])) {
$configurations['_'.$configuration->getAliasName()] = $configuration;
} else {
throw new \LogicException(sprintf('Multiple "%s" annotations are not allowed.', $configuration->getAliasName()));
}
}
}
return $configurations;
}
public static function getSubscribedEvents()
{
return array(
KernelEvents::CONTROLLER =>'onKernelController',
);
}
}
}
namespace Sensio\Bundle\FrameworkExtraBundle\EventListener
{
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
class ParamConverterListener implements EventSubscriberInterface
{
protected $manager;
protected $autoConvert;
public function __construct(ParamConverterManager $manager, $autoConvert = true)
{
$this->manager = $manager;
$this->autoConvert = $autoConvert;
}
public function onKernelController(FilterControllerEvent $event)
{
$controller = $event->getController();
$request = $event->getRequest();
$configurations = array();
if ($configuration = $request->attributes->get('_converters')) {
foreach (is_array($configuration) ? $configuration : array($configuration) as $configuration) {
$configurations[$configuration->getName()] = $configuration;
}
}
if (is_array($controller)) {
$r = new \ReflectionMethod($controller[0], $controller[1]);
} elseif (is_object($controller) && is_callable($controller,'__invoke')) {
$r = new \ReflectionMethod($controller,'__invoke');
} else {
$r = new \ReflectionFunction($controller);
}
if ($this->autoConvert) {
$configurations = $this->autoConfigure($r, $request, $configurations);
}
$this->manager->apply($request, $configurations);
}
private function autoConfigure(\ReflectionFunctionAbstract $r, Request $request, $configurations)
{
foreach ($r->getParameters() as $param) {
if (!$param->getClass() || $param->getClass()->isInstance($request)) {
continue;
}
$name = $param->getName();
if (!isset($configurations[$name])) {
$configuration = new ParamConverter(array());
$configuration->setName($name);
$configuration->setClass($param->getClass()->getName());
$configurations[$name] = $configuration;
} elseif (null === $configurations[$name]->getClass()) {
$configurations[$name]->setClass($param->getClass()->getName());
}
$configurations[$name]->setIsOptional($param->isOptional());
}
return $configurations;
}
public static function getSubscribedEvents()
{
return array(
KernelEvents::CONTROLLER =>'onKernelController',
);
}
}
}
namespace Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter
{
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;
interface ParamConverterInterface
{
public function apply(Request $request, ParamConverter $configuration);
public function supports(ParamConverter $configuration);
}
}
namespace Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter
{
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use DateTime;
class DateTimeParamConverter implements ParamConverterInterface
{
public function apply(Request $request, ParamConverter $configuration)
{
$param = $configuration->getName();
if (!$request->attributes->has($param)) {
return false;
}
$options = $configuration->getOptions();
$value = $request->attributes->get($param);
if (!$value && $configuration->isOptional()) {
return false;
}
if (isset($options['format'])) {
$date = DateTime::createFromFormat($options['format'], $value);
if (!$date) {
throw new NotFoundHttpException('Invalid date given.');
}
} else {
if (false === strtotime($value)) {
throw new NotFoundHttpException('Invalid date given.');
}
$date = new DateTime($value);
}
$request->attributes->set($param, $date);
return true;
}
public function supports(ParamConverter $configuration)
{
if (null === $configuration->getClass()) {
return false;
}
return'DateTime'=== $configuration->getClass();
}
}
}
namespace Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter
{
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\NoResultException;
class DoctrineParamConverter implements ParamConverterInterface
{
protected $registry;
public function __construct(ManagerRegistry $registry = null)
{
$this->registry = $registry;
}
public function apply(Request $request, ParamConverter $configuration)
{
$name = $configuration->getName();
$class = $configuration->getClass();
$options = $this->getOptions($configuration);
if (null === $request->attributes->get($name, false)) {
$configuration->setIsOptional(true);
}
if (false === $object = $this->find($class, $request, $options, $name)) {
if (false === $object = $this->findOneBy($class, $request, $options)) {
if ($configuration->isOptional()) {
$object = null;
} else {
throw new \LogicException('Unable to guess how to get a Doctrine instance from the request information.');
}
}
}
if (null === $object && false === $configuration->isOptional()) {
throw new NotFoundHttpException(sprintf('%s object not found.', $class));
}
$request->attributes->set($name, $object);
return true;
}
protected function find($class, Request $request, $options, $name)
{
if ($options['mapping'] || $options['exclude']) {
return false;
}
$id = $this->getIdentifier($request, $options, $name);
if (false === $id || null === $id) {
return false;
}
if (isset($options['repository_method'])) {
$method = $options['repository_method'];
} else {
$method ='find';
}
try {
return $this->getManager($options['entity_manager'], $class)->getRepository($class)->$method($id);
} catch (NoResultException $e) {
return;
}
}
protected function getIdentifier(Request $request, $options, $name)
{
if (isset($options['id'])) {
if (!is_array($options['id'])) {
$name = $options['id'];
} elseif (is_array($options['id'])) {
$id = array();
foreach ($options['id'] as $field) {
$id[$field] = $request->attributes->get($field);
}
return $id;
}
}
if ($request->attributes->has($name)) {
return $request->attributes->get($name);
}
if ($request->attributes->has('id') && !isset($options['id'])) {
return $request->attributes->get('id');
}
return false;
}
protected function findOneBy($class, Request $request, $options)
{
if (!$options['mapping']) {
$keys = $request->attributes->keys();
$options['mapping'] = $keys ? array_combine($keys, $keys) : array();
}
foreach ($options['exclude'] as $exclude) {
unset($options['mapping'][$exclude]);
}
if (!$options['mapping']) {
return false;
}
if (isset($options['id']) && null === $request->attributes->get($options['id'])) {
return false;
}
$criteria = array();
$em = $this->getManager($options['entity_manager'], $class);
$metadata = $em->getClassMetadata($class);
$mapMethodSignature = isset($options['repository_method'])
&& isset($options['map_method_signature'])
&& $options['map_method_signature'] === true;
foreach ($options['mapping'] as $attribute => $field) {
if ($metadata->hasField($field)
|| ($metadata->hasAssociation($field) && $metadata->isSingleValuedAssociation($field))
|| $mapMethodSignature) {
$criteria[$field] = $request->attributes->get($attribute);
}
}
if ($options['strip_null']) {
$criteria = array_filter($criteria, function ($value) { return !is_null($value); });
}
if (!$criteria) {
return false;
}
if (isset($options['repository_method'])) {
$repositoryMethod = $options['repository_method'];
} else {
$repositoryMethod ='findOneBy';
}
try {
if ($mapMethodSignature) {
return $this->findDataByMapMethodSignature($em, $class, $repositoryMethod, $criteria);
}
return $em->getRepository($class)->$repositoryMethod($criteria);
} catch (NoResultException $e) {
return;
}
}
private function findDataByMapMethodSignature($em, $class, $repositoryMethod, $criteria)
{
$arguments = array();
$repository = $em->getRepository($class);
$ref = new \ReflectionMethod($repository, $repositoryMethod);
foreach ($ref->getParameters() as $parameter) {
if (array_key_exists($parameter->name, $criteria)) {
$arguments[] = $criteria[$parameter->name];
} elseif ($parameter->isDefaultValueAvailable()) {
$arguments[] = $parameter->getDefaultValue();
} else {
throw new \InvalidArgumentException(sprintf('Repository method "%s::%s" requires that you provide a value for the "$%s" argument.', get_class($repository), $repositoryMethod, $parameter->name));
}
}
return $ref->invokeArgs($repository, $arguments);
}
public function supports(ParamConverter $configuration)
{
if (null === $this->registry || !count($this->registry->getManagers())) {
return false;
}
if (null === $configuration->getClass()) {
return false;
}
$options = $this->getOptions($configuration);
$em = $this->getManager($options['entity_manager'], $configuration->getClass());
if (null === $em) {
return false;
}
return !$em->getMetadataFactory()->isTransient($configuration->getClass());
}
protected function getOptions(ParamConverter $configuration)
{
return array_replace(array('entity_manager'=> null,'exclude'=> array(),'mapping'=> array(),'strip_null'=> false,
), $configuration->getOptions());
}
private function getManager($name, $class)
{
if (null === $name) {
return $this->registry->getManagerForClass($class);
}
return $this->registry->getManager($name);
}
}
}
namespace Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter
{
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ConfigurationInterface;
class ParamConverterManager
{
protected $converters = array();
protected $namedConverters = array();
public function apply(Request $request, $configurations)
{
if (is_object($configurations)) {
$configurations = array($configurations);
}
foreach ($configurations as $configuration) {
$this->applyConverter($request, $configuration);
}
}
protected function applyConverter(Request $request, ConfigurationInterface $configuration)
{
$value = $request->attributes->get($configuration->getName());
$className = $configuration->getClass();
if (is_object($value) && $value instanceof $className) {
return;
}
if ($converterName = $configuration->getConverter()) {
if (!isset($this->namedConverters[$converterName])) {
throw new \RuntimeException(sprintf("No converter named '%s' found for conversion of parameter '%s'.",
$converterName, $configuration->getName()
));
}
$converter = $this->namedConverters[$converterName];
if (!$converter->supports($configuration)) {
throw new \RuntimeException(sprintf("Converter '%s' does not support conversion of parameter '%s'.",
$converterName, $configuration->getName()
));
}
$converter->apply($request, $configuration);
return;
}
foreach ($this->all() as $converter) {
if ($converter->supports($configuration)) {
if ($converter->apply($request, $configuration)) {
return;
}
}
}
}
public function add(ParamConverterInterface $converter, $priority = 0, $name = null)
{
if ($priority !== null) {
if (!isset($this->converters[$priority])) {
$this->converters[$priority] = array();
}
$this->converters[$priority][] = $converter;
}
if (null !== $name) {
$this->namedConverters[$name] = $converter;
}
}
public function all()
{
krsort($this->converters);
$converters = array();
foreach ($this->converters as $all) {
$converters = array_merge($converters, $all);
}
return $converters;
}
}
}
namespace Sensio\Bundle\FrameworkExtraBundle\EventListener
{
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
class TemplateListener implements EventSubscriberInterface
{
protected $container;
public function __construct(ContainerInterface $container)
{
$this->container = $container;
}
public function onKernelController(FilterControllerEvent $event)
{
if (!is_array($controller = $event->getController())) {
return;
}
$request = $event->getRequest();
if (!$configuration = $request->attributes->get('_template')) {
return;
}
if (!$configuration->getTemplate()) {
$guesser = $this->container->get('sensio_framework_extra.view.guesser');
$configuration->setTemplate($guesser->guessTemplateName($controller, $request, $configuration->getEngine()));
}
$request->attributes->set('_template', $configuration->getTemplate());
$request->attributes->set('_template_vars', $configuration->getVars());
$request->attributes->set('_template_streamable', $configuration->isStreamable());
if (!$configuration->getVars()) {
$r = new \ReflectionObject($controller[0]);
$vars = array();
foreach ($r->getMethod($controller[1])->getParameters() as $param) {
$vars[] = $param->getName();
}
$request->attributes->set('_template_default_vars', $vars);
}
}
public function onKernelView(GetResponseForControllerResultEvent $event)
{
$request = $event->getRequest();
$parameters = $event->getControllerResult();
if (null === $parameters) {
if (!$vars = $request->attributes->get('_template_vars')) {
if (!$vars = $request->attributes->get('_template_default_vars')) {
return;
}
}
$parameters = array();
foreach ($vars as $var) {
$parameters[$var] = $request->attributes->get($var);
}
}
if (!is_array($parameters)) {
return $parameters;
}
if (!$template = $request->attributes->get('_template')) {
return $parameters;
}
$templating = $this->container->get('templating');
if (!$request->attributes->get('_template_streamable')) {
$event->setResponse($templating->renderResponse($template, $parameters));
} else {
$callback = function () use ($templating, $template, $parameters) {
return $templating->stream($template, $parameters);
};
$event->setResponse(new StreamedResponse($callback));
}
}
public static function getSubscribedEvents()
{
return array(
KernelEvents::CONTROLLER => array('onKernelController', -128),
KernelEvents::VIEW =>'onKernelView',
);
}
}
}
namespace Sensio\Bundle\FrameworkExtraBundle\EventListener
{
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
class HttpCacheListener implements EventSubscriberInterface
{
private $lastModifiedDates;
private $etags;
private $expressionLanguage;
public function __construct()
{
$this->lastModifiedDates = new \SplObjectStorage();
$this->etags = new \SplObjectStorage();
}
public function onKernelController(FilterControllerEvent $event)
{
$request = $event->getRequest();
if (!$configuration = $request->attributes->get('_cache')) {
return;
}
$response = new Response();
$lastModifiedDate ='';
if ($configuration->getLastModified()) {
$lastModifiedDate = $this->getExpressionLanguage()->evaluate($configuration->getLastModified(), $request->attributes->all());
$response->setLastModified($lastModifiedDate);
}
$etag ='';
if ($configuration->getETag()) {
$etag = hash('sha256', $this->getExpressionLanguage()->evaluate($configuration->getETag(), $request->attributes->all()));
$response->setETag($etag);
}
if ($response->isNotModified($request)) {
$event->setController(function () use ($response) {
return $response;
});
} else {
if ($etag) {
$this->etags[$request] = $etag;
}
if ($lastModifiedDate) {
$this->lastModifiedDates[$request] = $lastModifiedDate;
}
}
}
public function onKernelResponse(FilterResponseEvent $event)
{
$request = $event->getRequest();
if (!$configuration = $request->attributes->get('_cache')) {
return;
}
$response = $event->getResponse();
if (!in_array($response->getStatusCode(), array(200, 203, 300, 301, 302, 304, 404, 410))) {
return;
}
if (null !== $age = $configuration->getSMaxAge()) {
if (!is_numeric($age)) {
$now = microtime(true);
$age = ceil(strtotime($configuration->getSMaxAge(), $now) - $now);
}
$response->setSharedMaxAge($age);
}
if (null !== $age = $configuration->getMaxAge()) {
if (!is_numeric($age)) {
$now = microtime(true);
$age = ceil(strtotime($configuration->getMaxAge(), $now) - $now);
}
$response->setMaxAge($age);
}
if (null !== $configuration->getExpires()) {
$date = \DateTime::createFromFormat('U', strtotime($configuration->getExpires()), new \DateTimeZone('UTC'));
$response->setExpires($date);
}
if (null !== $configuration->getVary()) {
$response->setVary($configuration->getVary());
}
if ($configuration->isPublic()) {
$response->setPublic();
}
if ($configuration->isPrivate()) {
$response->setPrivate();
}
if (isset($this->lastModifiedDates[$request])) {
$response->setLastModified($this->lastModifiedDates[$request]);
unset($this->lastModifiedDates[$request]);
}
if (isset($this->etags[$request])) {
$response->setETag($this->etags[$request]);
unset($this->etags[$request]);
}
$event->setResponse($response);
}
public static function getSubscribedEvents()
{
return array(
KernelEvents::CONTROLLER =>'onKernelController',
KernelEvents::RESPONSE =>'onKernelResponse',
);
}
private function getExpressionLanguage()
{
if (null === $this->expressionLanguage) {
if (!class_exists('Symfony\Component\ExpressionLanguage\ExpressionLanguage')) {
throw new \RuntimeException('Unable to use expressions as the Symfony ExpressionLanguage component is not installed.');
}
$this->expressionLanguage = new ExpressionLanguage();
}
return $this->expressionLanguage;
}
}
}
namespace Sensio\Bundle\FrameworkExtraBundle\EventListener
{
use Sensio\Bundle\FrameworkExtraBundle\Security\ExpressionLanguage;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
class SecurityListener implements EventSubscriberInterface
{
private $tokenStorage;
private $authChecker;
private $language;
private $trustResolver;
private $roleHierarchy;
public function __construct(SecurityContextInterface $securityContext = null, ExpressionLanguage $language = null, AuthenticationTrustResolverInterface $trustResolver = null, RoleHierarchyInterface $roleHierarchy = null, TokenStorageInterface $tokenStorage = null, AuthorizationCheckerInterface $authChecker = null)
{
$this->tokenStorage = $tokenStorage ?: $securityContext;
$this->authChecker = $authChecker ?: $securityContext;
$this->language = $language;
$this->trustResolver = $trustResolver;
$this->roleHierarchy = $roleHierarchy;
}
public function onKernelController(FilterControllerEvent $event)
{
$request = $event->getRequest();
if (!$configuration = $request->attributes->get('_security')) {
return;
}
if (null === $this->tokenStorage || null === $this->trustResolver) {
throw new \LogicException('To use the @Security tag, you need to install the Symfony Security bundle.');
}
if (null === $this->tokenStorage->getToken()) {
throw new \LogicException('To use the @Security tag, your controller needs to be behind a firewall.');
}
if (null === $this->language) {
throw new \LogicException('To use the @Security tag, you need to use the Security component 2.4 or newer and to install the ExpressionLanguage component.');
}
if (!$this->language->evaluate($configuration->getExpression(), $this->getVariables($request))) {
throw new AccessDeniedException(sprintf('Expression "%s" denied access.', $configuration->getExpression()));
}
}
private function getVariables(Request $request)
{
$token = $this->tokenStorage->getToken();
if (null !== $this->roleHierarchy) {
$roles = $this->roleHierarchy->getReachableRoles($token->getRoles());
} else {
$roles = $token->getRoles();
}
$variables = array('token'=> $token,'user'=> $token->getUser(),'object'=> $request,'request'=> $request,'roles'=> array_map(function ($role) { return $role->getRole(); }, $roles),'trust_resolver'=> $this->trustResolver,'auth_checker'=> $this->authChecker,
);
return array_merge($request->attributes->all(), $variables);
}
public static function getSubscribedEvents()
{
return array(KernelEvents::CONTROLLER =>'onKernelController');
}
}
}
namespace Sensio\Bundle\FrameworkExtraBundle\Configuration
{
interface ConfigurationInterface
{
public function getAliasName();
public function allowArray();
}
}
namespace Sensio\Bundle\FrameworkExtraBundle\Configuration
{
abstract class ConfigurationAnnotation implements ConfigurationInterface
{
public function __construct(array $values)
{
foreach ($values as $k => $v) {
if (!method_exists($this, $name ='set'.$k)) {
throw new \RuntimeException(sprintf('Unknown key "%s" for annotation "@%s".', $k, get_class($this)));
}
$this->$name($v);
}
}
}
}