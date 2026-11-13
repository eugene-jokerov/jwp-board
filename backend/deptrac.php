<?php

/*
 * Правила зависимостей между слоями бэкенда (Deptrac).
 *
 * Запуск: make deptrac; граф правил — make deptrac-graph.
 *
 * Архитектура модулей гексагональная. Слои модуля заданы директориями
 * app/Modules/<Module>/{Domain,Application,Infrastructure} для каждого модуля
 * из $modulesList: новый модуль регистрируется там и сразу получает правила.
 * Направление зависимостей — только внутрь, к Domain:
 *
 *   Infrastructure → Application → Domain
 *
 * Дополнительно Appendix (app/Http, routes, bootstrap, config — входные адаптеры
 * Laravel) → Infrastructure/Application/Domain модулей и Bus → Application/Domain.
 *
 * Чужой код модулю доступен только через объявленные контракты: «модуль →
 * [модули, чей Domain ему можно читать]» ($moduleDependencies). Application
 * и Infrastructure модуля видят Domain соседей, но не их Application/
 * Infrastructure; связь проходящих через них событий — только через шину.
 * Тесты (Tests) могут зависеть от любого кода.
 */

use Deptrac\Deptrac\Contract\Config\Collector\BoolConfig;
use Deptrac\Deptrac\Contract\Config\Collector\ClassLikeConfig;
use Deptrac\Deptrac\Contract\Config\Collector\DirectoryConfig;
use Deptrac\Deptrac\Contract\Config\DeptracConfig;
use Deptrac\Deptrac\Contract\Config\Layer;
use Deptrac\Deptrac\Contract\Config\Ruleset;

// Доменные модули: app/Modules/<Module>/. Новый модуль регистрируется здесь.
$modulesList = ['Direction', 'History', 'Task', 'User'];

// Модули, чей Domain модулю разрешено читать: «модуль → [модули]».
$moduleDependencies = [
    'Direction' => ['History', 'Task', 'User'],
    'Task' => ['Direction', 'History', 'User'],
];

// Слои модулей: «модуль → [Domain, Application, Infrastructure]» (по порядку ролей).
$layers = [];
foreach ($modulesList as $module) {
    $layers[$module] = [];
    foreach (['Domain', 'Application', 'Infrastructure'] as $role) {
        $layers[$module][] = Layer::withName("{$module}:{$role}")->collectors(
            DirectoryConfig::create("app/Modules/{$module}/{$role}/"),
        );
    }
}

// Входные адаптеры Laravel вне модулей.
$apiControllers = Layer::withName('ApiControllers')->collectors(
    DirectoryConfig::create('app/Http/Controllers/Api/'),
);
// Контракты API (запросы и ресурсы), которые нужны больше чем одному модулю
// и поэтому живут вне его зеркала: модульные живут в Infrastructure модуля.
$apiRequests = Layer::withName('ApiContracts')->collectors(
    DirectoryConfig::create('app/Http/Requests/'),
    DirectoryConfig::create('app/Http/Resources/'),
);
// Публичный сайт (лендинг, юридические документы): страницы вне API-контракта.
$pages = Layer::withName('Pages')->collectors(
    DirectoryConfig::create('app/Pages/'),
);
$httpMiddleware = Layer::withName('HttpMiddleware')->collectors(
    DirectoryConfig::create('app/Http/Middleware/'),
);
$webControllers = Layer::withName('WebControllers')->collectors(
    DirectoryConfig::create('app/Http/Controllers/Web/'),
);
$baseController = Layer::withName('BaseController')->collectors(
    ClassLikeConfig::create('^App\\\\Http\\\\Controllers\\\\Controller$'),
);
$services = Layer::withName('Services')->collectors(
    DirectoryConfig::create('app/Services/'),
);
$bus = Layer::withName('Bus')->collectors(
    DirectoryConfig::create('app/Shared/Application/'),
);
$sharedInfrastructure = Layer::withName('SharedInfrastructure')->collectors(
    DirectoryConfig::create('app/Shared/Infrastructure/'),
);
$filament = Layer::withName('Filament')->collectors(
    DirectoryConfig::create('app/Filament/'),
);
$filamentProviders = Layer::withName('FilamentProviders')->collectors(
    DirectoryConfig::create('app/Providers/Filament/'),
);
$providers = Layer::withName('Providers')->collectors(
    ClassLikeConfig::create('^App\\\\Providers\\\\[^\\\\]+$'),
);
$bootstrap = Layer::withName('Bootstrap')->collectors(
    DirectoryConfig::create('bootstrap/'),
);
// config/*.php — конфигурация фреймворка; классы-адаптеры конфигурации
// модулей (app/Modules/**/Infrastructure/Config) к слою Config не относятся.
$configLayer = Layer::withName('Config')->collectors(
    BoolConfig::create(
        must: [DirectoryConfig::create('config/')],
        mustNot: [ClassLikeConfig::create('^App\\Modules\\')],
    ),
);
$database = Layer::withName('Database')->collectors(
    DirectoryConfig::create('database/'),
);
$routes = Layer::withName('Routes')->collectors(
    DirectoryConfig::create('routes/'),
);
$tests = Layer::withName('Tests')->collectors(
    DirectoryConfig::create('tests/'),
);

return static function (DeptracConfig $config) use (
    $moduleDependencies,
    $layers,
    $apiControllers,
    $apiRequests,
    $httpMiddleware,
    $webControllers,
    $baseController,
    $services,
    $pages,
    $bus,
    $sharedInfrastructure,
    $filament,
    $filamentProviders,
    $providers,
    $bootstrap,
    $configLayer,
    $database,
    $routes,
    $tests,
): void {
    $config->paths('app', 'bootstrap', 'routes', 'config', 'database', 'tests');

    // Слои регистрируются по одному: Deptrac сравнивает их по объектам.
    $layerConfigs = [];
    foreach ($layers as $moduleLayers) {
        foreach ($moduleLayers as $moduleLayer) {
            $layerConfigs[] = $moduleLayer;
        }
    }
    foreach ([
        $apiControllers,
        $apiRequests,
        $httpMiddleware,
        $webControllers,
        $baseController,
        $services,
        $pages,
        $bus,
        $sharedInfrastructure,
        $filament,
        $filamentProviders,
        $providers,
        $bootstrap,
        $configLayer,
        $database,
        $routes,
        $tests,
    ] as $layer) {
        $layerConfigs[] = $layer;
    }
    foreach ($layerConfigs as $layerConfig) {
        $config->layers($layerConfig);
    }

    // Правило: слой может зависеть только от перечисленных слоёв.
    $rule = static fn (Layer $layer, array $allowed): Ruleset => Ruleset::forLayer($layer)->accesses(...$allowed);

    $applied = array_keys($layers);

    // Зависимости модулей друг от друга разрешены только на уровне Domain:
    // «модуль → [модули, чей Domain ему можно читать]». Слои Infrastructure
    // соседей (Eloquent-адаптеры, фабрики, HTTP-контроллеры) модулю не видны.
    $domains = static function (string $module) use ($moduleDependencies, $layers): array {
        $result = [$layers[$module][0]];
        foreach ($moduleDependencies[$module] ?? [] as $dependency) {
            $result[] = $layers[$dependency][0];
        }

        return $result;
    };

    // Слои выбранных ролей у набора модулей: роли по индексу — 0 Domain,
    // 1 Application, 2 Infrastructure.
    $ofModules = static function (array $modules, array $roles) use ($layers): array {
        $result = [];
        foreach ($modules as $module) {
            foreach ($roles as $role) {
                $result[] = $layers[$module][$role];
            }
        }

        return $result;
    };

    // Демо-код модулей — фабрики в его Infrastructure/Persistence/Factories.
    // Это тот же входной демо-код, что и сиды в Database, поэтому они делят
    // общий помощник Database\Factories\FactoryOwners.
    $moduleFactories = $ofModules($applied, [2]);

    // Входные адаптеры работают через сценарии модулей: видят Application
    // (команды, запросы, результаты) и Domain (модели, value objects,
    // исключения). Infrastructure модуля нужна им для общих HTTP-контрактов
    // и адаптеров канала (ресурсы, запросы, сессии, токены): это такие же
    // адаптеры на границе, и правило входа разрешает их использовать.
    $moduleAdapters = $ofModules($applied, [0, 1, 2]);

    foreach ($layers as $module => $moduleLayers) {
        [$domain, $application, $infrastructure] = $moduleLayers;

        $config->rulesets(
            // Domain — ядро модуля: не знает про фреймворк, шину, Application
            // и Infrastructure, а из соседей читает только разрешённые домены.
            $rule($domain, $domains($module)),
            // Application — сценарии: работают с Domain своего модуля и
            // разрешённых домены соседей, живут вне фреймворка и адаптеров.
            $rule($application, [$domain, $application, $bus, ...$domains($module)]),
            // Infrastructure — адаптеры модуля: Domain своего модуля и разрешённых
            // соседей, Application своего модуля (команды, запросы, результаты,
            // которые адаптер переводит в HTTP или Eloquent), шина и общие
            // API-контракты (вход модуля может переиспользовать валидацию API)
            // и демо-код database/ (фабрикам нужен владелец-пользователь).
            // Чужие Infrastructure и Application — недоступны: сосед доступен
            // только через объявленный Domain-контракт.
            $rule($infrastructure, [$domain, $application, $infrastructure, $bus, $apiRequests, $database, ...$domains($module)]),
        );
    }

    // Входные адаптеры Laravel и служебная инфраструктура.
    $config->rulesets(
        $rule($apiControllers, [$bus, $apiRequests, $baseController, ...$moduleAdapters]),
        // Запросы и ресурсы API ссылаются друг на друга (фильтры, вложенные
        // ресурсы) и на Domain модулей.
        $rule($apiRequests, [$apiRequests, ...$ofModules($applied, [0])]),
        $rule($baseController, [$bus]),
        $rule($webControllers, [$bus, $baseController, $pages, ...$moduleAdapters]),
        $rule($httpMiddleware, [$bus, ...$ofModules($applied, [0])]),
        // Служебные сервисы Laravel-слоя (публичный сайт) не знают модулей.
        $rule($pages, []),
        // Шина: типы CommandBus/QueryBus не зависят от модулей, кроме проверки
        // режима «только чтение», которая читает доменную модель User.
        $rule($bus, [$layers['User'][0]]),
        $rule($sharedInfrastructure, [$bus]),
        $rule($filament, [$bus, $filament, ...$moduleAdapters]),
        $rule($filamentProviders, [$filament, $httpMiddleware]),
        $rule($providers, [$bus, $sharedInfrastructure]),
        $rule($bootstrap, [$providers, $sharedInfrastructure, $httpMiddleware]),
        $rule($configLayer, [$configLayer]),
        // Сиды и миграции — композиция на границе: собирают данные модулей.
        $rule($database, [$bus, ...$moduleAdapters, $apiRequests, ...$moduleFactories]),
        $rule($routes, [$apiControllers, $httpMiddleware]),
        $rule($tests, [...$layerConfigs, $apiRequests]),
    );
};
