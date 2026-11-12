/**
 * Попап «Направления» — настройки направлений стратегического уровня.
 *
 * Направление — родитель инициатив, поэтому список правится здесь целиком:
 * добавление (название + цвет), редактирование (название + цвет), удаление.
 *
 * Правила:
 * - цвет выбирается только из палитры направлений (красного в ней нет: он
 *   зарезервирован под блокировки и ошибки);
 * - удалить направление можно, только если в нём нет карточек ни в одной
 *   колонке — иначе инициативы остались бы без родителя;
 * - удаление подтверждается прямо в строке направления: системные диалоги
 *   на web не работают, а поведение должно быть одинаковым на всех платформах.
 *
 * Перекраску карточек направления выполняет экран — здесь правятся только
 * сами направления.
 *
 * Режим «только чтение» попап не различает: ограничение держит сервер, и его
 * отказ (403 с текстом в `errors.account`) приходит тостом, как любой другой
 * отказ сохранения или удаления. Доступные действия клиент не гасит заранее:
 * попап выглядит одинаково у всех пользователей.
 */

import { useState } from 'react';
import { Modal, Pressable, ScrollView, Text, View } from 'react-native';

import { CircleCheck, Pencil, Trash2 } from 'lucide-react-native';

import { TextField } from '../../components/TextField';
import { useFormErrorToast } from '../../notifications/useFormErrorToast';
import { DIRECTION_COLORS, directionColorHex, firstFreeDirectionColor } from '../directionColors';
import type { Direction } from '../types';

/** Цвет служебных иконок строки направления. */
const ROW_ICON_COLOR = '#6b7280';

interface DirectionsModalProps {
  visible: boolean;
  directions: readonly Direction[];
  /**
   * Лимит направлений с сервера (`meta.limit`); null — ещё не известен.
   * По нему попап показывает счётчик «Направления n из k» и гасит кнопку
   * создания: новое направление сервер не примет (422).
   */
  limit: number | null;
  /** Добавить направление; текст ошибки или null при успехе. */
  onAdd: (title: string, color: string) => Promise<string | null>;
  /** Изменить название и цвет; текст ошибки или null при успехе. */
  onUpdate: (id: number, title: string, color: string) => Promise<string | null>;
  /** Удалить направление: правило проверено экраном до вызова. */
  onDelete: (id: number) => Promise<string | null>;
  /** Причина, по которой направление удалить нельзя; null — можно. */
  deleteReason: (direction: Direction) => string | null;
  onClose: () => void;
}

interface ColorPickerProps {
  value: string;
  /** Цвета, занятые другими направлениями: повторно выбрать их нельзя. */
  usedColors: readonly string[];
  onChange: (color: string) => void;
}

/**
 * Сетка палитры: двадцать кружков, выбранный обведён и с галочкой.
 *
 * Цвет уникален у каждого направления — иначе свимлайны и полоски карточек
 * не различить. Занятый другим направлением кружок недоступен: он гасится,
 * не нажимается и озвучивается как «уже используется».
 */
function ColorPicker({ value, usedColors, onChange }: ColorPickerProps) {
  return (
    <View className="flex-row flex-wrap gap-2">
      {DIRECTION_COLORS.map((color) => {
        const selected = color.key === value;
        const used = !selected && usedColors.includes(color.key);

        return (
          <Pressable
            key={color.key}
            accessibilityRole="radio"
            accessibilityLabel={`${color.label}${used ? ' (уже используется)' : ''}`}
            accessibilityState={{ selected, disabled: used }}
            disabled={used}
            onPress={() => onChange(color.key)}
            hitSlop={2}
            className={[
              'h-7 w-7 items-center justify-center rounded-full',
              selected ? 'border-2 border-app' : '',
              used ? 'opacity-25' : '',
            ].join(' ')}
          >
            <View
              className="h-5 w-5 items-center justify-center rounded-full"
              style={{ backgroundColor: directionColorHex(color.key) }}
            >
              {selected ? <CircleCheck size={12} color="#ffffff" /> : null}
            </View>
          </Pressable>
        );
      })}
    </View>
  );
}

interface DirectionRowProps {
  direction: Direction;
  /** Цвета других направлений — подсказка в палитре. */
  usedColors: readonly string[];
  /** Причина запрета удаления; null — можно удалять. */
  deleteReason: string | null;
  onSave: (title: string, color: string) => Promise<string | null>;
  onDelete: () => Promise<string | null>;
}

/**
 * Строка направления с тремя режимами: просмотр, редактирование и
 * подтверждение удаления. Режим живёт в самой строке.
 */
function DirectionRow({ direction, usedColors, deleteReason, onSave, onDelete }: DirectionRowProps) {
  const [mode, setMode] = useState<'view' | 'edit' | 'confirmDelete'>('view');
  const [title, setTitle] = useState(direction.title);
  const [color, setColor] = useState(direction.color);
  const [titleError, setTitleError] = useState<string | null>(null);
  /**
   * Ошибки строки (сохранение и удаление) показываются тостом — единым стилем
   * со всеми ошибками доски: сообщение не зависит от режима строки и остаётся
   * видимым, даже если строка свернулась в режим просмотра.
   */
  const { show: showFormError, clear: clearFormError } = useFormErrorToast({
    formKey: `direction-${direction.id}`,
    title: 'Не удалось сохранить направление',
  });
  // Пока идёт запрос, кнопки строки заблокированы: повторный клик не нужен.
  const [saving, setSaving] = useState(false);

  const titleEmpty = title.trim() === '';

  const startEdit = () => {
    setTitle(direction.title);
    setColor(direction.color);
    setTitleError(null);
    clearFormError();
    setMode('edit');
  };

  const submit = async () => {
    const trimmed = title.trim();

    if (trimmed === '') {
      setTitleError('Укажите название.');
      return;
    }

    setSaving(true);
    const error = await onSave(trimmed, color);
    setSaving(false);

    if (error !== null) {
      showFormError(error);
      return;
    }

    setMode('view');
  };

  const remove = async () => {
    setSaving(true);
    const error = await onDelete();
    setSaving(false);

    if (error !== null) {
      showFormError(error);
    }

    setMode('view');
  };

  if (mode === 'edit') {
    return (
      <View className="gap-2 rounded-xl border border-border bg-surface p-3">
        <TextField
          label="Название"
          value={title}
          onChangeText={(value) => {
            setTitle(value);
            setTitleError(null);
            clearFormError();
          }}
          error={titleError}
          placeholder="Например: Продукт"
          autoFocus
        />

        <View className="gap-1.5">
          <Text className="text-sm font-medium text-app">Цвет</Text>

          <ColorPicker
            value={color}
            usedColors={usedColors}
            onChange={(value) => {
              setColor(value);
              clearFormError();
            }}
          />

          {color === direction.color ? null : (
            <Text className="text-[11px] text-muted">Карточки направления перекрасятся вместе с ним.</Text>
          )}
        </View>

        <View className="flex-row gap-2">
          <Pressable
            accessibilityRole="button"
            onPress={() => setMode('view')}
            className="h-10 flex-1 items-center justify-center rounded-xl bg-card active:opacity-80"
          >
            <Text className="text-sm font-semibold text-app">Отмена</Text>
          </Pressable>

          <Pressable
            accessibilityRole="button"
            accessibilityState={{ disabled: titleEmpty || saving }}
            disabled={titleEmpty || saving}
            onPress={() => void submit()}
            className={[
              'h-10 flex-1 items-center justify-center rounded-xl bg-app active:opacity-90',
              titleEmpty || saving ? 'opacity-60' : '',
            ].join(' ')}
          >
            <Text className="text-sm font-semibold text-white">Сохранить</Text>
          </Pressable>
        </View>
      </View>
    );
  }

  return (
    <View className="gap-1.5 rounded-xl border border-border px-3 py-2">
      <View className="flex-row items-center gap-2">
        <View
          className="h-3 w-3 shrink-0 rounded-full"
          style={{ backgroundColor: directionColorHex(direction.color) }}
        />

        <Text className="flex-1 text-sm text-app" numberOfLines={2}>
          {direction.title}
        </Text>

        {/* Действия строки всегда видны: ограничение прав держит сервер, и его
            отказ приходит тостом — так же, как отказ удаления непустого
            направления. */}
        <Pressable
          accessibilityRole="button"
          accessibilityLabel={`Изменить направление «${direction.title}»`}
          onPress={startEdit}
          hitSlop={6}
          className="h-7 w-7 items-center justify-center rounded-lg active:bg-surface"
        >
          <Pencil size={14} color={ROW_ICON_COLOR} />
        </Pressable>

        <Pressable
          accessibilityRole="button"
          accessibilityLabel={`Удалить направление «${direction.title}»`}
          accessibilityState={{ disabled: deleteReason !== null }}
          disabled={deleteReason !== null}
          onPress={() => setMode('confirmDelete')}
          hitSlop={6}
          className={[
            'h-7 w-7 items-center justify-center rounded-lg active:bg-err-bg',
            deleteReason === null ? '' : 'opacity-40',
          ].join(' ')}
        >
          <Trash2 size={14} color={ROW_ICON_COLOR} />
        </Pressable>
      </View>

      {/* Причина запрета удаления считается по карточкам направления, без запроса
          к серверу, поэтому показывается подписью под строкой: кнопка удаления
          погашена, а объяснение стоит рядом с ней. */}
      {deleteReason === null ? null : <Text className="text-[11px] text-subtle">{deleteReason}</Text>}

      {mode === 'confirmDelete' ? (
        <View className="gap-2 rounded-lg bg-err-bg p-2.5">
          <Text className="text-xs font-semibold text-err-fg">Удалить направление «{direction.title}»?</Text>

          <View className="flex-row gap-2">
            <Pressable
              accessibilityRole="button"
              onPress={() => setMode('view')}
              className="h-9 flex-1 items-center justify-center rounded-lg bg-card active:opacity-80"
            >
              <Text className="text-xs font-semibold text-app">Отмена</Text>
            </Pressable>

            <Pressable
              accessibilityRole="button"
              accessibilityState={{ disabled: saving }}
              disabled={saving}
              onPress={() => void remove()}
              className={[
                'h-9 flex-1 items-center justify-center rounded-lg bg-err-fg active:opacity-90',
                saving ? 'opacity-60' : '',
              ].join(' ')}
            >
              <Text className="text-xs font-semibold text-white">Удалить</Text>
            </Pressable>
          </View>
        </View>
      ) : null}
    </View>
  );
}

interface DirectionCreatorProps {
  usedColors: readonly string[];
  onCreate: (title: string, color: string) => Promise<string | null>;
  /** Свернуть форму (после успешного добавления или по кнопке «Отмена»). */
  onClose: () => void;
}

/**
 * Форма создания направления: название и цвет из палитры.
 *
 * Цвет по умолчанию — первый свободный: занятые цвета выбрать нельзя, и
 * подставлять занятый было бы сразу ошибкой. Форму монтируют заново на каждое
 * открытие, поэтому стартовый цвет берётся один раз из пропсов.
 */
function DirectionCreator({ usedColors, onCreate, onClose }: DirectionCreatorProps) {
  const [title, setTitle] = useState('');
  const [color, setColor] = useState(() => firstFreeDirectionColor(usedColors));
  const [titleError, setTitleError] = useState<string | null>(null);
  /** Ошибка создания показывается тостом — так же, как остальные ошибки доски. */
  const { show: showFormError, clear: clearFormError } = useFormErrorToast({
    formKey: 'direction-create',
    title: 'Не удалось добавить направление',
  });
  const [saving, setSaving] = useState(false);

  const titleEmpty = title.trim() === '';

  const submit = async () => {
    const trimmed = title.trim();

    if (trimmed === '') {
      setTitleError('Укажите название.');
      return;
    }

    setSaving(true);
    const error = await onCreate(trimmed, color);
    setSaving(false);

    if (error !== null) {
      showFormError(error);
      return;
    }

    setTitle('');
    setTitleError(null);
    clearFormError();
    // Закрываем форму: при следующем открытии она сама возьмёт первый свободный цвет.
    onClose();
  };

  return (
    <View className="gap-2 rounded-xl border border-border bg-surface p-3">
      <Text className="text-sm font-semibold text-app">Новое направление</Text>

      <TextField
        label="Название"
        value={title}
        onChangeText={(value) => {
          setTitle(value);
          setTitleError(null);
          clearFormError();
        }}
        error={titleError}
        placeholder="Например: Продукт"
      />

      <View className="gap-1.5">
        <Text className="text-sm font-medium text-app">Цвет</Text>

        <ColorPicker
          value={color}
          usedColors={usedColors}
          onChange={(value) => {
            setColor(value);
            clearFormError();
          }}
        />
      </View>

      <View className="flex-row gap-2">
        <Pressable
          accessibilityRole="button"
          onPress={onClose}
          className="h-10 flex-1 items-center justify-center rounded-xl bg-card active:opacity-80"
        >
          <Text className="text-sm font-semibold text-app">Отмена</Text>
        </Pressable>

        <Pressable
          accessibilityRole="button"
          accessibilityState={{ disabled: titleEmpty || saving }}
          disabled={titleEmpty || saving}
          onPress={() => void submit()}
          className={[
            'h-10 flex-1 items-center justify-center rounded-xl bg-app active:opacity-90',
            titleEmpty || saving ? 'opacity-60' : '',
          ].join(' ')}
        >
          <Text className="text-sm font-semibold text-white">Добавить</Text>
        </Pressable>
      </View>
    </View>
  );
}

export function DirectionsModal({
  visible,
  directions,
  limit,
  onAdd,
  onUpdate,
  onDelete,
  deleteReason,
  onClose,
}: DirectionsModalProps) {
  // Форма создания скрыта по умолчанию: список направлений — главное содержимое,
  // а форма открывается кнопкой «Добавить направление».
  const [creating, setCreating] = useState(false);

  // Лимит известен только после загрузки доски: до этого счётчик не показываем,
  // а создание не блокируем — сервер всё равно проверит лимит сам.
  const limitReached = limit !== null && directions.length >= limit;

  /** Заголовок со счётчиком: «Направления 3 из 15»; без лимита — просто «Направления». */
  const counter = limit === null ? null : `${directions.length} из ${limit}`;

  /**
   * Прочитать список направлений пользователю: счётчик и подпись формы стоят
   * в одном месте, чтобы скринридер не собирал фразу из разных блоков.
   */
  const headerLabel = counter === null ? 'Направления' : `Направления ${counter}`;

  /** Закрыть попап и заодно свернуть форму, чтобы следующий вход начинался со списка. */
  const closeAll = () => {
    setCreating(false);
    onClose();
  };

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={closeAll}>
      <View className="flex-1 items-center justify-center bg-black/40 px-4">
        <View className="w-full max-w-lg gap-4 rounded-2xl bg-card p-5">
          <View className="gap-1">
            <View className="flex-row items-baseline justify-between gap-3">
              <Text accessibilityRole="header" accessibilityLabel={headerLabel} className="text-lg font-bold text-app">
                Направления
              </Text>

              {/* Счётчик «n из k»: сколько направлений уже заведено и сколько
                  всего доступно. Число лимита приходит с сервера (meta.limit). */}
              {counter === null ? null : <Text className="text-xs font-semibold text-muted">{counter}</Text>}
            </View>

            <Text className="text-xs text-muted">
              Направление — родитель инициатив стратегического уровня. Удалить можно только пустое направление.
            </Text>
          </View>

          {/* Создание — над списком: кнопка раскрывается формой на своём же месте,
              а сам список остаётся ниже и не прыгает по высоте. При достигнутом
              лимите кнопка погашена: сервер ответил бы 422, поэтому сразу
              объясняем причину, а не ждём ошибки. Ограничение прав кнопка не
              учитывает: отказ сервера (403) придёт тостом из формы. */}
          {creating ? (
            <DirectionCreator
              usedColors={directions.map((item) => item.color)}
              onCreate={onAdd}
              onClose={() => setCreating(false)}
            />
          ) : (
            <>
              <Pressable
                accessibilityRole="button"
                accessibilityState={{ disabled: limitReached }}
                disabled={limitReached}
                onPress={() => setCreating(true)}
                className={[
                  'h-10 items-center justify-center rounded-xl border border-app bg-card active:bg-surface',
                  limitReached ? 'opacity-60' : '',
                ].join(' ')}
              >
                <Text className={['text-sm font-semibold', limitReached ? 'text-muted' : 'text-app'].join(' ')}>
                  Добавить направление
                </Text>
              </Pressable>

              {limitReached ? (
                <Text className="text-[11px] text-muted">
                  Достигнут лимит направлений. Удалите ненужное, чтобы добавить новое.
                </Text>
              ) : null}
            </>
          )}

          <ScrollView className="max-h-[60vh]" contentContainerClassName="gap-1.5">
            {directions.length === 0 && !creating ? (
              <Text className="text-sm text-muted">Направлений пока нет — добавьте первое.</Text>
            ) : null}

            {directions.length > 0
              ? directions.map((direction) => (
                  <DirectionRow
                    key={direction.id}
                    direction={direction}
                    usedColors={directions.filter((item) => item.id !== direction.id).map((item) => item.color)}
                    deleteReason={deleteReason(direction)}
                    onSave={(title, color) => onUpdate(direction.id, title, color)}
                    onDelete={() => onDelete(direction.id)}
                  />
                ))
              : null}
          </ScrollView>

          <Pressable
            accessibilityRole="button"
            onPress={closeAll}
            className="h-11 items-center justify-center rounded-xl bg-app active:opacity-90"
          >
            <Text className="text-sm font-semibold text-white">Готово</Text>
          </Pressable>
        </View>
      </View>
    </Modal>
  );
}
