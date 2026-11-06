<?php

namespace Database\Seeders;

use App\Modules\Direction\Domain\Direction;
use App\Modules\Direction\Domain\ValueObjects\DirectionColor;
use App\Modules\Task\Domain\Task;
use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardType;
use App\Modules\User\Domain\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Доска одного тестового пользователя — как у живого человека.
 *
 * Логин: test@jwp-board.local, пароль: password.
 *
 * Состав задан требованиями: 5 направлений и 149 инициатив (5 в беклоге,
 * 4 в работе, 40 готово, 100 отменено). Тактический и операционный уровни
 * пустыми быть не могут: проект живёт только под инициативой, задача — только
 * под проектом. Поэтому доска достраивается вниз: под инициативами появляются
 * проекты, под проектами — задачи, и на нижних уровнях карточек кратно больше.
 *
 * Колонки детей согласованы с колонкой родителя: ColumnTransitionPolicy не даёт
 * вывести карточку из работы, пока у неё есть дети в работе, поэтому доска из
 * сида не должна содержать таких состояний. Отменённая инициатива может быть
 * без детей вовсе — «отменили, не начиная».
 *
 * Иерархия заполняется вручную — так же, как это делает доменный сервис
 * TaskPlacement: direction_id проекта и задачи наследуется от родителя.
 *
 * Даты правдоподобные: направления и инициативы появились в течение года,
 * проекты и задачи — позже своих родителей, карточки в работе обновлялись
 * недавно. Так доска выглядит историей, а не набором строк из одной секунды.
 *
 * Данные пишутся батчами с заранее распределёнными id: Eloquent-create на
 * тысячу с лишним карточек — тысяча с лишним запросов, батч-вставка — единицы.
 */
class UserBoardSeeder extends Seeder
{
    /**
     * Email тестового пользователя.
     */
    public const string EMAIL = 'test@jwp-board.local';

    /**
     * Пароль тестового пользователя (открытым текстом — только для локальной разработки).
     */
    public const string PASSWORD = 'password';

    /**
     * Прежний демо-логин: убирается вместе со своей доской, чтобы не путать вход.
     */
    private const string LEGACY_EMAIL = 'demo@jwp-board.local';

    /**
     * Пользователь, для которого собирается доска.
     */
    private User $user;

    /**
     * Сколько инициатив в какой колонке — суммы заданы требованиями.
     *
     * @var array<string, int>
     */
    private const array INITIATIVES_BY_COLUMN = [
        'backlog' => 5,
        'in_progress' => 4,
        'complete' => 40,
        'canceled' => 100,
    ];

    /**
     * Направления пользователя: здоровье, работа, деньги, близкие и развитие.
     *
     * @var list<array{title: string, color: DirectionColor}>
     */
    private const array DIRECTIONS = [
        ['title' => 'Здоровье и энергия', 'color' => DirectionColor::Green],
        ['title' => 'Карьера и профессия', 'color' => DirectionColor::Blue],
        ['title' => 'Финансы и накопления', 'color' => DirectionColor::Teal],
        ['title' => 'Семья и близкие', 'color' => DirectionColor::Pink],
        ['title' => 'Образование и развитие', 'color' => DirectionColor::Violet],
    ];

    /**
     * Инициативы в беклоге: только задумано, сроков нет.
     *
     * @var list<string>
     */
    private const array BACKLOG_INITIATIVES = [
        'Пробежать полумарафон в следующем сезоне',
        'Собрать домашнюю мастерскую',
        'Выучить испанский до уровня B1',
        'Научиться играть на гитаре',
        'Съездить в отпуск на две недели без ноутбука',
        'Сделать ремонт в кабинете',
        'Завести традицию воскресных прогулок',
        'Начать вести учёт личных финансов',
        'Разобрать архив фотографий за десять лет',
        'Оформить медицинскую страховку на весь год',
    ];

    /**
     * Инициативы в работе: по одной-две на направление.
     *
     * @var list<string>
     */
    private const array IN_PROGRESS_INITIATIVES = [
        'Выйти на стабильные 8 часов сна',
        'Перейти на старшую роль в команде',
        'Собрать финансовую подушку на полгода',
        'Обустроить квартиру под совместную жизнь',
        'Закрыть профессиональную сертификацию',
        'Выстроить регулярные тренировки три раза в неделю',
    ];

    /**
     * Готовые инициативы: результат зафиксирован.
     *
     * @var list<string>
     */
    private const array COMPLETE_INITIATIVES = [
        'Сбросить 8 килограммов без жёстких диет',
        'Проверить здоровье на полном чекапе',
        'Вылечить зубы и поставить коронки',
        'Поставить регулярный режим дня',
        'Наладить питание: домашняя еда пять дней в неделю',
        'Победить боль в спине и наладить осанку',
        'Перейти на удалённую работу',
        'Получить повышение до ведущего специалиста',
        'Выстроить отношения с новым руководителем',
        'Запустить свой первый пет-проект',
        'Обновить портфолио и резюме',
        'Пройти собеседования в три компании',
        'Настроить рабочий кабинет дома',
        'Возглавить команду из пяти человек',
        'Выступить на профильной конференции',
        'Написать серию статей по своей теме',
        'Накопить первый миллион',
        'Закрыть кредитную карту полностью',
        'Собрать резерв на непредвиденные расходы',
        'Открыть инвестиционный счёт и вкладывать регулярно',
        'Разобраться с налогами и вычетами',
        'Отложить деньги на обучение ребёнка',
        'Настроить автоматические переводы на накопления',
        'Пересмотреть все подписки и убрать лишние',
        'Договориться с банком о снижении ставки по ипотеке',
        'Сделать годовой бюджет семьи',
        'Отпраздновать годовщину большой поездкой',
        'Наладить еженедельный семейный вечер без телефонов',
        'Сделать ремонт на кухне',
        'Провести отпуск с семьёй без работы',
        'Организовать помощь родителям по здоровью',
        'Договориться о разделении домашних обязанностей',
        'Завести общий календарь семейных дел',
        'Подготовить документы на случай форс-мажора',
        'Отучиться на курсе повышения квалификации',
        'Сдать профильный экзамен',
        'Прочитать 20 книг за год',
        'Пройти курс по системному дизайну',
        'Освоить новый технологический стек',
        'Сдать на права категории B',
        'Закончить курсы английского уровня C1',
        'Собрать библиотеку профессиональных материалов',
        'Научиться плавать кролем на 1000 метров',
        'Освоить работу с аналитическими инструментами',
        'Пройти обучение по финансовому планированию',
        'Завершить длинный онлайн-курс по профессии',
    ];

    /**
     * Отменённые инициативы: не сложилось по срокам, деньгам или приоритетам.
     *
     * @var list<string>
     */
    private const array CANCELED_INITIATIVES = [
        'Подготовиться к марафону весной',
        'Перейти на вегетарианское питание',
        'Заняться кроссфитом пять раз в неделю',
        'Вернуться в большой спорт',
        'Купить абонемент в загородный клуб',
        'Сделать операцию по коррекции зрения',
        'Пробежать трейл на 50 километров',
        'Начать практику йоги каждый день',
        'Оборудовать домашний спортзал',
        'Сдать нормативы ГТО',
        'Завести собаку и бегать с ней по утрам',
        'Сменить профессию на аналитика данных',
        'Уйти во фриланс на полную ставку',
        'Переехать в другую страну по работе',
        'Открыть собственную студию',
        'Запустить стартап с партнёром',
        'Совмещать две работы одновременно',
        'Получить MBA',
        'Перейти в продуктовую компанию',
        'Построить личный бренд в социальных сетях',
        'Вести видеоблог о профессии',
        'Стать ментором в крупном сообществе',
        'Сделать бизнес на обучении',
        'Купить франшизу',
        'Инвестировать в недвижимость за рубежом',
        'Взять ипотеку на вторую квартиру',
        'Открыть накопительный счёт в зарубежном банке',
        'Купить автомобиль в кредит',
        'Вложиться в акции одной компании',
        'Сдавать квартиру посуточно',
        'Собрать капитал на пассивный доход к 40 годам',
        'Купить коммерческое помещение',
        'Заняться трейдингом профессионально',
        'Перевести все сбережения в валюту',
        'Взять займ на развитие бизнеса',
        'Купить земельный участок под строительство',
        'Сделать капитальный ремонт в загородном доме',
        'Купить дачу для круглогодичного проживания',
        'Переехать за город насовсем',
        'Оформить кредит на обучение',
        'Сделать большую свадьбу',
        'Устроить семейное путешествие на год',
        'Купить второй автомобиль для семьи',
        'Переделать всю квартиру по дизайн-проекту',
        'Завести троих детей до 35 лет',
        'Организовать собственный семейный бизнес',
        'Перевезти родителей в город',
        'Собрать большую родню на юбилей',
        'Сделать дом гостевым',
        'Начать вести семейный блог',
        'Провести месяц в путешествии с детьми',
        'Купить дом у моря',
        'Стать опекуном приюта для животных',
        'Заниматься волонтёрством каждые выходные',
        'Завести коз и кур в деревне',
        'Перестроить отношения с родственниками',
        'Пройти семейную терапию полным курсом',
        'Открыть семейную школу',
        'Перевести детей на домашнее обучение',
        'Получить второе высшее образование',
        'Выучить китайский до свободного уровня',
        'Пройти программу MBA в Европе',
        'Сдать экзамен CFA',
        'Получить сертификат по управлению проектами',
        'Выучить французский с нуля',
        'Сдать на права категории C',
        'Пройти курс по искусственному интеллекту',
        'Освоить профессию пилота',
        'Сдать экзамен по бухгалтерскому учёту',
        'Выучить язык жестов',
        'Прочитать 100 книг за год',
        'Пройти онлайн-магистратуру',
        'Получить научную степень',
        'Научиться играть на фортепиано',
        'Выучить латынь',
        'Пройти курс актёрского мастерства',
        'Сдать IELTS на 8.0',
        'Освоить цирковое искусство',
        'Пройти обучение на инструктора по йоге',
        'Выучить язык программирования Rust',
        'Пойти на курсы сомелье',
        'Освоить профессию дизайнера интерьеров',
        'Получить лицензию на оружие',
        'Пройти обучение дайвингу до уровня инструктора',
        'Записаться на курсы астрономии',
        'Изучить историю искусств в университете',
        'Получить сертификат по кибербезопасности',
        'Пройти стажировку за границей',
        'Отучиться на пилота дронов',
        'Освоить блокчейн-разработку',
        'Пройти курс по ораторскому мастерству',
        'Выучить итальянский за год',
        'Получить квалификацию тренера по плаванию',
        'Сдать экзамен по статистике',
        'Пройти обучение по коучингу',
        'Освоить фотографию профессионально',
        'Научиться писать сценарии',
        'Пройти курс по керамике',
    ];

    /**
     * Названия инициатив для колонки.
     *
     * @return list<string>
     */
    private static function initiativeTitles(CardColumn $column): array
    {
        return match ($column) {
            CardColumn::Backlog => self::BACKLOG_INITIATIVES,
            CardColumn::InProgress => self::IN_PROGRESS_INITIATIVES,
            CardColumn::Complete => self::COMPLETE_INITIATIVES,
            CardColumn::Canceled => self::CANCELED_INITIATIVES,
        };
    }

    /**
     * Карточки без направления: то, что записано «на будущее».
     *
     * @return list<array{title: string, type: CardType, column: CardColumn}>
     */
    private static function cardsWithoutDirection(): array
    {
        return [
            ['title' => 'Разобрать рабочий стол и ящики', 'type' => CardType::Task, 'column' => CardColumn::Backlog],
            ['title' => 'Оформить загранпаспорт', 'type' => CardType::Task, 'column' => CardColumn::Backlog],
            ['title' => 'Найти хорошего стоматолога', 'type' => CardType::Task, 'column' => CardColumn::Backlog],
            ['title' => 'Разобрать подписки на сервисы', 'type' => CardType::Task, 'column' => CardColumn::InProgress],
            ['title' => 'Забрать посылку из пункта выдачи', 'type' => CardType::Task, 'column' => CardColumn::Complete],
            ['title' => 'Разобрать почту и отписаться от рассылок', 'type' => CardType::Task, 'column' => CardColumn::Complete],
            ['title' => 'Отменить ненужную страховку', 'type' => CardType::Task, 'column' => CardColumn::Canceled],
            ['title' => 'Личные планы на полгода', 'type' => CardType::Project, 'column' => CardColumn::Backlog],
            ['title' => 'Административные дела по документам', 'type' => CardType::Project, 'column' => CardColumn::InProgress],
        ];
    }

    /**
     * Следующий свободный id карточки: нумерация продолжает таблицу после сборки.
     */
    private int $nextCardId = 1;

    /**
     * Собранные строки по уровням доски — для батч-вставки.
     *
     * @var list<array<string, mixed>>
     */
    private array $initiativeRows = [];

    /**
     * @var list<array<string, mixed>>
     */
    private array $projectRows = [];

    /**
     * @var list<array<string, mixed>>
     */
    private array $taskRows = [];

    /**
     * Заполнить доску тестового пользователя.
     */
    public function run(): void
    {
        $this->removeLegacyUser();

        $this->user = User::query()->updateOrCreate(
            ['email' => self::EMAIL],
            [
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
            ],
        );

        // Сид идемпотентен: доска пересобирается с нуля, а не дополняется второй.
        // Карточки уходят первыми: у них есть ссылки на направления и друг на друга.
        $userId = (int) $this->user->id;

        Task::query()->where('user_id', $userId)->delete();
        Direction::query()->where('user_id', $userId)->delete();

        $this->initiativeRows = [];
        $this->projectRows = [];
        $this->taskRows = [];
        // Нумерация продолжает таблицу: id в базе общие для всех пользователей.
        $this->nextCardId = (int) Task::query()->max('id') + 1;

        foreach (self::DIRECTIONS as $index => $direction) {
            $this->seedDirection($index, $direction['title'], $direction['color']);
        }

        $this->seedCardsWithoutDirection();

        $this->insertRows(Task::class, $this->initiativeRows);
        $this->insertRows(Task::class, $this->projectRows);
        $this->insertRows(Task::class, $this->taskRows);
    }

    /**
     * Удалить пользователя прежнего демо-сида вместе с его доской.
     */
    private function removeLegacyUser(): void
    {
        $legacy = User::query()->where('email', self::LEGACY_EMAIL)->first();

        if ($legacy === null) {
            return;
        }

        Task::query()->where('user_id', $legacy->id)->delete();
        Direction::query()->where('user_id', $legacy->id)->delete();
        $legacy->delete();
    }

    /**
     * Направление с его инициативами и поддеревом под каждой из них.
     *
     * Очередь каждой колонки нарезается на равные доли по направлениям, поэтому
     * у всех направлений своя доля в беклоге, в работе, готовых и отменённых —
     * как на настоящей доске.
     */
    private function seedDirection(int $index, string $title, DirectionColor $color): void
    {
        $createdAt = Carbon::now()->subDays(400 - $index * 12);

        $direction = Direction::query()->create([
            'user_id' => $this->user->id,
            'title' => $title,
            'color' => $color->value,
        ]);

        $direction->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        foreach (CardColumn::ordered() as $column) {
            $titles = self::initiativeTitles($column);
            $count = self::INITIATIVES_BY_COLUMN[$column->value];
            // Доля направления: очередь колонки делится на всех, остаток забирает первый.
            $perDirection = (int) ceil($count / count(self::DIRECTIONS));
            $start = $index * $perDirection;
            $end = min($count, $start + $perDirection);

            for ($i = $start; $i < $end; $i++) {
                $this->seedInitiative($direction, $column, $titles[$i % count($titles)], $createdAt);
            }
        }
    }

    /**
     * Инициатива и её поддерево: проекты с задачами.
     *
     * Отменённая инициатива может остаться без детей — её закрыли, не начиная.
     * У работающей инициативы обязательно есть проекты в работе: иначе карточка
     * «в работе» без движения, что для демо-доски выглядит неестественно.
     */
    private function seedInitiative(Direction $direction, CardColumn $column, string $title, Carbon $directionCreatedAt): void
    {
        $createdAt = $this->between($directionCreatedAt, Carbon::now()->subDays(30));
        $updatedAt = $this->updatedAt($createdAt, $column);

        $id = $this->nextCardId++;

        $this->initiativeRows[] = [
            'id' => $id,
            'user_id' => $this->user->id,
            'direction_id' => $direction->id,
            'initiative_id' => null,
            'project_id' => null,
            'type' => CardType::Initiative->value,
            'column' => $column->value,
            'title' => $title,
            'description' => $this->description($column, $title),
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];

        foreach ($this->projectPlan($column, $title) as $plan) {
            $this->seedProject($direction, $id, $updatedAt, $plan['title'], $plan['column']);
        }
    }

    /**
     * Проект под инициативой и его задачи.
     *
     * Тактический и операционный уровни заполняются по плану проекта: колонка
     * проекта определяет, какие карточки под ним вообще уместны.
     */
    private function seedProject(Direction $direction, int $initiativeId, Carbon $initiativeUpdatedAt, string $title, CardColumn $column): void
    {
        $createdAt = $this->after($initiativeUpdatedAt, Carbon::now()->subDays(7));
        $updatedAt = $this->updatedAt($createdAt, $column);
        $id = $this->nextCardId++;

        $this->projectRows[] = [
            'id' => $id,
            'user_id' => $this->user->id,
            'direction_id' => $direction->id,
            'initiative_id' => $initiativeId,
            'project_id' => null,
            'type' => CardType::Project->value,
            'column' => $column->value,
            'title' => $title,
            'description' => $this->description($column, $title),
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];

        foreach ($this->taskPlan($column, $title) as $plan) {
            $taskCreatedAt = $this->after($updatedAt, Carbon::now()->subDay());
            $taskId = $this->nextCardId++;

            $this->taskRows[] = [
                'id' => $taskId,
                'user_id' => $this->user->id,
                'direction_id' => $direction->id,
                'initiative_id' => $initiativeId,
                'project_id' => $id,
                'type' => CardType::Task->value,
                'column' => $plan['column']->value,
                'title' => $plan['title'],
                'description' => $this->description($plan['column'], $plan['title']),
                'created_at' => $taskCreatedAt,
                'updated_at' => $this->updatedAt($taskCreatedAt, $plan['column']),
            ];
        }
    }

    /**
     * Какие проекты появляются под инициативой.
     *
     * Число проектов и их колонки зависят от колонки инициативы: у работающей
     * инициативы проекты в основном в работе, у готовой — готовые, у
     * отменённой — отменённые, а часть отменённых вообще остаётся без детей.
     *
     * @return list<array{title: string, column: CardColumn}>
     */
    private function projectPlan(CardColumn $initiativeColumn, string $initiativeTitle): array
    {
        $blueprints = match ($initiativeColumn) {
            // В работе: несколько параллельных направлений работы и то, что ждёт очереди.
            CardColumn::InProgress => [
                ['Этап: подготовка и сбор данных', CardColumn::Complete],
                ['Этап: пилот и первые результаты', CardColumn::InProgress],
                ['Этап: основной объём работ', CardColumn::InProgress],
                ['Этап: тиражирование и автоматизация', CardColumn::Backlog],
            ],
            // Беклог: только задумки — ни одна не начата.
            CardColumn::Backlog => [
                ['Черновик плана и оценка сроков', CardColumn::Backlog],
                ['Исследование: что уже есть на рынке', CardColumn::Backlog],
            ],
            // Готово: этапы пройдены, отдельные работы остались в отмене.
            CardColumn::Complete => [
                ['Этап: подготовка', CardColumn::Complete],
                ['Этап: основной объём работ', CardColumn::Complete],
                ['Этап: итоги и закрепление результата', CardColumn::Complete],
            ],
            // Отмена: часть инициатив закрыта без работ вовсе.
            CardColumn::Canceled => mt_rand(0, 2) === 0
                ? []
                : [
                    ['Начатый, но свёрнутый этап', CardColumn::Canceled],
                    ['Этап, отменённый до старта', CardColumn::Canceled],
                ],
        };

        $plan = [];

        foreach ($blueprints as $blueprint) {
            $plan[] = [
                'title' => $initiativeTitle.'. '.$blueprint[0],
                'column' => $blueprint[1],
            ];
        }

        return $plan;
    }

    /**
     * Какие задачи появляются под проектом.
     *
     * На операционном уровне карточек заметно больше, чем на тактическом:
     * у работающего проекта это и сделанное, и текущее, и ожидающее.
     *
     * @return list<array{title: string, column: CardColumn}>
     */
    private function taskPlan(CardColumn $projectColumn, string $projectTitle): array
    {
        $blueprints = match ($projectColumn) {
            // В работе: текущие задачи, немного готового и что-то уже отвалилось.
            CardColumn::InProgress => [
                ['Собрать вводные и договориться о сроках', CardColumn::Complete],
                ['Согласовать план с участниками', CardColumn::Complete],
                ['Сделать основную часть работы', CardColumn::InProgress],
                ['Проверить результат и собрать замечания', CardColumn::InProgress],
                ['Разобрать замечания и доработать', CardColumn::Backlog],
                ['Отложенная подзадача: разобрать позже', CardColumn::Canceled],
            ],
            // Беклог: только заготовки задач.
            CardColumn::Backlog => [
                ['Сформулировать задачу и критерии готовности', CardColumn::Backlog],
                ['Оценить трудозатраты', CardColumn::Backlog],
            ],
            // Готово: задачи закрыты, пара отменена по ходу дела.
            CardColumn::Complete => [
                ['Собрать исходные данные', CardColumn::Complete],
                ['Выполнить работу', CardColumn::Complete],
                ['Оформить результат', CardColumn::Complete],
                ['Отменённая по ходу задача', CardColumn::Canceled],
            ],
            // Отмена: либо задач не было, либо они отменены вместе с проектом.
            CardColumn::Canceled => [
                ['Отменена вместе с проектом', CardColumn::Canceled],
                ['Не успели начать', CardColumn::Canceled],
            ],
        };

        $plan = [];

        foreach ($blueprints as $blueprint) {
            $plan[] = [
                'title' => $projectTitle.'. '.$blueprint[0],
                'column' => $blueprint[1],
            ];
        }

        return $plan;
    }

    /**
     * Карточки без направления: личный беклог и задачи «на потом».
     *
     * Такие карточки в домене допустимы (проект без инициативы и задача без
     * проекта) и у живого пользователя встречаются всегда — он записывает дела,
     * ещё не привязанные к направлению.
     */
    private function seedCardsWithoutDirection(): void
    {
        foreach (self::cardsWithoutDirection() as $card) {
            $createdAt = $this->after(Carbon::now()->subDays(20), Carbon::now()->subDays(3));
            $row = [
                'id' => $this->nextCardId++,
                'user_id' => $this->user->id,
                'direction_id' => null,
                'initiative_id' => null,
                'project_id' => null,
                'type' => $card['type']->value,
                'column' => $card['column']->value,
                'title' => $card['title'],
                'description' => $this->description($card['column'], $card['title']),
                'created_at' => $createdAt,
                'updated_at' => $this->updatedAt($createdAt, $card['column']),
            ];

            $this->rowsFor($card['type'])[] = $row;
        }
    }

    /**
     * Список строк уровня, в который складываются карточки этого типа.
     *
     * Возвращает ссылку на свойство, чтобы вызывающий код мог дописать строку.
     *
     * @return list<array<string, mixed>>
     */
    private function &rowsFor(CardType $type): array
    {
        switch ($type) {
            case CardType::Initiative:
                return $this->initiativeRows;
            case CardType::Project:
                return $this->projectRows;
            case CardType::Task:
                return $this->taskRows;
        }
    }

    /**
     * Вставить строки батчем.
     *
     * Пачки по 200 строк: одна вставка на тысячу карточек упёрлась бы в лимиты
     * размера запроса, а построчный insert был бы слишком медленным.
     *
     * @param  class-string<Direction|Task>  $model
     * @param  list<array<string, mixed>>  $rows
     */
    private function insertRows(string $model, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        /** @var Direction|Task $instance */
        $instance = new $model;
        $table = $instance->getTable();

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    /**
     * Случайный момент между двумя датами — для правдоподобной истории карточек.
     */
    private function between(Carbon $from, Carbon $to): Carbon
    {
        $start = $from->getTimestamp();
        $end = max($start + Carbon::HOURS_PER_DAY, $to->getTimestamp());

        return Carbon::createFromTimestamp(mt_rand($start, $end));
    }

    /**
     * Момент позже указанного, но не позже предела и не в будущем.
     */
    private function after(Carbon $from, Carbon $limit): Carbon
    {
        $start = $from->getTimestamp() + Carbon::HOURS_PER_DAY;
        $end = max($start, min($limit->getTimestamp(), Carbon::now()->getTimestamp()));

        return Carbon::createFromTimestamp(mt_rand($start, $end));
    }

    /**
     * Когда карточку последний раз трогали — по её колонке.
     *
     * «В работе» обновлялась считанные часы назад, беклог лежит нетронутым,
     * готовое и отменённое закрыто в прошлом.
     */
    private function updatedAt(Carbon $createdAt, CardColumn $column): Carbon
    {
        $now = Carbon::now();

        $updatedAt = match ($column) {
            // Работающая карточка трогалась недавно: за последние три дня.
            CardColumn::InProgress => $this->after($now->copy()->subDays(3), $now),
            CardColumn::Backlog => $createdAt->copy()->addHours(mt_rand(1, 48)),
            CardColumn::Complete => $this->after($createdAt, $now->copy()->subDays(10)),
            CardColumn::Canceled => $this->after($createdAt, $now->copy()->subDays(30)),
        };

        return $updatedAt->lessThan($createdAt) ? $createdAt : $updatedAt;
    }

    /**
     * Описание карточки: её суть плюс заметка, не противоречащая колонке.
     *
     * Для отменённых пишется, почему не сложилось, для готовых — что получилось.
     */
    private function description(CardColumn $column, string $title): string
    {
        $note = match ($column) {
            CardColumn::Backlog => 'Записано на будущее, сроки пока не определены.',
            CardColumn::InProgress => 'Работа идёт, ближайшая контрольная точка — через две недели.',
            CardColumn::Complete => 'Закрыто, результат зафиксирован.',
            CardColumn::Canceled => 'Отменено: приоритеты сместились, возвращаться не планирую.',
        };

        return sprintf('%s. %s', $title, $note);
    }
}
