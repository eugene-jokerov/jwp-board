/**
 * Модалка создания карточки.
 *
 * Два режима:
 * - «с родителем из свимлайна» — родитель предопределён, цвет уже известен;
 * - «с выбором родителя» — карточку уровня создают кнопкой в шапке уровня.
 *   У направления на стратегическом уровне родитель обязателен, а у тактического
 *   и операционного есть опция «без родителя»: проект без инициативы и задача без
 *   проекта допустимы (карточка получает служебный нейтральный цвет c0).
 *
 * Степень проработки («Идея» / «На реализацию») выбирается только для беклога:
 * карточка, которую создают сразу в работе или в закрытой колонке, идеей не
 * бывает, и сервер такой флаг обнуляет. Значение по умолчанию зависит от
 * уровня: инициатива и проект открываются как «Идея», задача — как «На
 * реализацию» (см. `defaultIdea`).
 *
 * Приоритет выбирается всегда — низкий, средний или высокий, по умолчанию
 * средний: он не зависит ни от колонки, ни от уровня карточки.
 */

import { useEffect, useState } from 'react';
import { KeyboardAvoidingView, Modal, Platform, Pressable, ScrollView, Text, View } from 'react-native';

import { SegmentedSwitch } from '../../components/SegmentedSwitch';
import { TextField } from '../../components/TextField';
import { useNotifications } from '../../notifications/NotificationContext';
import { useFormErrorToast } from '../../notifications/useFormErrorToast';
import { DatePickerField } from './DatePickerField';
import { UNPARENTED_CARD_COLOR, directionColorHex } from '../directionColors';
import { toDeadlineDate } from '../deadline';
import type { CardKind, CardPriority, ColumnId, ParentOptionModel } from '../types';
import {
  DEFAULT_CARD_PRIORITY,
  DESCRIPTION_MAX_LENGTH,
  KIND_LABEL,
  KIND_LEVEL,
  PARENT_LABEL,
  PRIORITIES,
  SLUG_MAX_LENGTH,
  WORK_STAGE_LABEL,
} from '../types';

export interface CardDraft {
  kind: CardKind;
  /** Свимлайн, из которого открыли форму: там родитель предопределён. */
  laneId: number | null;
  /** Цвет, который унаследует карточка из свимлайна (null — без цвета). */
  laneColor: string | null;
  /** Название свимлайна — показываем в шапке формы. */
  laneTitle: string | null;
  /**
   * Допустимые родители: карточки в работе, а для инициативы — направления.
   * null — выбор не нужен.
   */
  parentOptions: ParentOptionModel[] | null;
  /** Можно ли создать карточку без родителя (проект без инициативы, задача без проекта). */
  allowNoParent: boolean;
  /** Подпись опции «без родителя» в списке: у тактики и операционного уровня она своя. */
  noParentLabel: string;
  columnLabel: string;
  /** Колонка, в которую попадёт карточка. */
  columnId: ColumnId;
  /** Начальный приоритет новой карточки (по умолчанию — средний). */
  priority: CardPriority;
  /** Название уровня — для шапки формы. */
  levelLabel: string;
}

interface CardFormModalProps {
  draft: CardDraft | null;
  /**
   * Создать карточку; текст ошибки сервера или null при успехе.
   * `slug` — кодовое название или null (поле не заполнено).
   * `deadline` — дата в формате API (`YYYY-MM-DD`) или null.
   * `idea` — степень проработки: true — идея; вне беклога всегда false.
   * `priority` — приоритет карточки: низкий, средний или высокий.
   */
  onCreate: (
    draft: CardDraft,
    title: string,
    slug: string | null,
    description: string | null,
    parentId: number | null,
    deadline: string | null,
    idea: boolean,
    priority: CardPriority,
  ) => Promise<string | null>;
  onClose: () => void;
}

interface ParentOptionProps {
  label: string;
  color: string | null;
  selected: boolean;
  onPress: () => void;
}

function ParentOption({ label, color, selected, onPress }: ParentOptionProps) {
  return (
    <Pressable
      accessibilityRole="radio"
      accessibilityState={{ selected }}
      onPress={onPress}
      className={[
        'flex-row items-center gap-2 rounded-xl border px-3 py-2',
        selected ? 'border-app bg-surface' : 'border-border',
      ].join(' ')}
    >
      <View
        className="h-3 w-3 rounded-full border border-border"
        style={{ backgroundColor: directionColorHex(color) }}
      />

      <Text className="flex-1 text-sm text-app" numberOfLines={1}>
        {label}
      </Text>

      {selected ? <Text className="text-xs font-semibold text-app">✓</Text> : null}
    </Pressable>
  );
}

function parentErrorText(kind: CardKind): string {
  return kind === 'initiative' ? 'Выберите направление.' : 'Выберите родителя (только карточки в колонке «В работе»).';
}

/**
 * Степень проработки по умолчанию для карточки данного типа.
 *
 * На стратегическом и тактическом уровнях (инициатива, проект) новую карточку
 * обычно заводят как замысел — «Идея»; на операционном уровне задачу берут в
 * работу уже продуманной, поэтому по умолчанию она «На реализацию». Выбор всё
 * равно виден в форме, значение — лишь начальное.
 */
function defaultIdea(kind: CardKind): boolean {
  return KIND_LEVEL[kind] !== 'operational';
}

/**
 * Опция степени проработки: «Идея» или «На реализацию».
 *
 * Выбор показывается только для беклога: в остальных колонках карточка не
 * бывает идеей, и сервер обнуляет флаг при создании.
 */
function StageOption({
  label,
  hint,
  selected,
  onPress,
}: {
  label: string;
  hint: string;
  selected: boolean;
  onPress: () => void;
}) {
  return (
    <Pressable
      accessibilityRole="radio"
      accessibilityState={{ selected }}
      onPress={onPress}
      className={['gap-0.5 rounded-xl border px-3 py-2', selected ? 'border-app bg-surface' : 'border-border'].join(
        ' ',
      )}
    >
      <View className="flex-row items-center gap-2">
        <Text className="flex-1 text-sm text-app">{label}</Text>

        {selected ? <Text className="text-xs font-semibold text-app">✓</Text> : null}
      </View>

      <Text className="text-[11px] text-subtle">{hint}</Text>
    </Pressable>
  );
}

/** Пояснение под пустым списком родителей: у направлений оно не нужно. */
function parentEmptyHint(kind: CardKind): string | null {
  return kind === 'initiative' ? null : 'Нет карточек в колонке «В работе» — выбрать нечего.';
}

export function CardFormModal({ draft, onCreate, onClose }: CardFormModalProps) {
  const [title, setTitle] = useState('');
  /** Кодовое название необязательно: пустое поле отправляется как null. */
  const [slug, setSlug] = useState('');
  /** Описание необязательно: пустое поле отправляется как null. */
  const [description, setDescription] = useState('');
  /** Дедлайн необязателен: null — карточка без срока. */
  const [deadline, setDeadline] = useState<string | null>(null);
  /**
   * Степень проработки: true — идея. Начальное значение зависит от уровня
   * карточки (см. `defaultIdea`): инициатива и проект открываются как «Идея»,
   * задача — как «На реализацию».
   */
  const [idea, setIdea] = useState(() => defaultIdea(draft?.kind ?? 'initiative'));
  /**
   * Приоритет карточки. Начальное значение — из черновика (там всегда средний),
   * поэтому новая форма открывается на «Среднем», как указано в требованиях.
   */
  const [priority, setPriority] = useState<CardPriority>(() => draft?.priority ?? DEFAULT_CARD_PRIORITY);
  const [parentId, setParentId] = useState<number | null>(null);
  const [titleError, setTitleError] = useState<string | null>(null);
  /**
   * Отправлять без родителя нажали, но родителя нет: причина считается на
   * рендере (`parentChoiceMissing`), а не хранится в состоянии — иначе её
   * пришлось бы сбрасывать при каждом выборе.
   */
  const [parentTouched, setParentTouched] = useState(false);
  /** Ключ тоста «родитель обязателен, но не выбран». */
  const parentMissingKey = 'card-create-parent-missing';
  /**
   * Ошибка сервера (валидация иерархии, лимит, нет прав) показывается тостом —
   * единым стилем со всеми ошибками доски. Тост «липкий» и снимается, как только
   * поле поправили или форму закрыли.
   */
  const { show: showFormError, clear: clearFormError } = useFormErrorToast({
    formKey: 'card-create',
    title: 'Не удалось создать карточку',
  });
  const { notifyError, dismissKey } = useNotifications();
  const [saving, setSaving] = useState(false);

  const parentOptions = draft?.parentOptions ?? null;
  /** Родитель обязателен, когда список есть, а карточка без него невозможна. */
  const parentRequired = parentOptions !== null && draft?.allowNoParent === false;
  const selectedParent = parentOptions?.find((card) => card.id === parentId) ?? null;

  /**
   * Почему родителя выбрать нельзя: список пуст, а без родителя такую карточку
   * сервер не создаст. Это блокировка, поэтому показывается сразу.
   */
  const parentBlockedReason =
    parentRequired && parentOptions !== null && parentOptions.length === 0
      ? (parentEmptyHint(draft?.kind ?? 'initiative') ?? 'Выбрать нечего.')
      : null;

  /**
   * Причина показа тоста о невыбранном родителе.
   *
   * `parentBlockedReason` виден сразу — выбирать нечего, поэтому ждать нажатия
   * кнопки незачем. «Родитель не выбран» показывается только после попытки
   * сохранить: до этого выбор просто ещё не сделан.
   */
  const parentChoiceMissing =
    parentBlockedReason ??
    (parentTouched && parentRequired && selectedParent === null ? parentErrorText(draft?.kind ?? 'initiative') : null);

  /**
   * Показывать/снимать тост о родителе.
   *
   * Хук стоит до раннего возврата `draft === null`: порядок хуков не должен
   * зависеть от пропсов.
   */
  useEffect(() => {
    if (parentChoiceMissing === null) {
      dismissKey(parentMissingKey);

      return;
    }

    notifyError(parentChoiceMissing, { title: 'Родитель не выбран', duration: 'sticky', key: parentMissingKey });
  }, [parentChoiceMissing, notifyError, dismissKey, parentMissingKey]);

  // Состояние формы сбрасываем при закрытии, а не эффектом при открытии.
  const close = () => {
    setTitle('');
    setSlug('');
    setDescription('');
    setDeadline(null);
    // Возвращаем значение по умолчанию, а не «последний выбор»: повторное
    // открытие формы (в том числе для карточки другого уровня) должно снова
    // начинаться со степени проработки по умолчанию.
    setIdea(defaultIdea(draft?.kind ?? 'initiative'));
    // Приоритет тоже возвращается к умолчанию: повторное открытие формы не
    // должно наследовать выбор предыдущей карточки.
    setPriority(draft?.priority ?? DEFAULT_CARD_PRIORITY);
    setParentId(null);
    setTitleError(null);
    setParentTouched(false);
    clearFormError();
    setSaving(false);
    onClose();
  };

  if (draft === null) {
    return null;
  }

  const previewColor = draft.laneColor ?? selectedParent?.color ?? null;
  /**
   * Степень проработки выбирается только для беклога: остальные колонки
   * создают карточку сразу в работе, и «идея» там не имеет смысла.
   */
  const stageSelectable = draft.columnId === 'backlog';

  // Безымянную карточку не создаём: кнопка ждёт название, а родителя —
  // только если выбор обязателен (направление для инициативы, инициатива для проекта).
  const createDisabled = saving || title.trim() === '' || (parentRequired && selectedParent === null);

  const submit = async () => {
    const titleValue = title.trim();
    const slugValue = slug.trim();
    const descriptionValue = description.trim();
    const parentInvalid = parentRequired && selectedParent === null;

    if (titleValue === '') {
      setTitleError('Укажите название.');
    }

    if (parentInvalid) {
      // Причину покажет тост: она вычислена на рендере и уже на экране.
      setParentTouched(true);
    }

    if (titleValue === '' || parentInvalid) {
      return;
    }

    setSaving(true);
    const error = await onCreate(
      draft,
      titleValue,
      slugValue === '' ? null : slugValue,
      descriptionValue === '' ? null : descriptionValue,
      draft.laneId ?? parentId,
      toDeadlineDate(deadline),
      stageSelectable && idea,
      priority,
    );
    setSaving(false);

    if (error !== null) {
      showFormError(error);
      return;
    }

    close();
  };

  return (
    <Modal visible transparent animationType="fade" onRequestClose={close}>
      {/* Форма длинная и на низком экране не помещается целиком, поэтому карточка
          ограничена высотой экрана: шапка и кнопки закреплены, прокручивается
          только тело. KeyboardAvoidingView поднимает форму над клавиатурой на iOS.
          Скругление задаём через `radius`, а не `overflow-hidden`: на web
          вертикальный ScrollView и так обрезает по горизонтали (overflow-x:
          hidden в react-native-web), и фокусная обводка полей, которая рисуется
          чуть шире рамки, пропадала бы по краям. Поэтому же у прокручиваемого
          тела есть горизонтальные отступы: обводке нужно место внутри клипа. */}
      <KeyboardAvoidingView className="flex-1" behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <View className="flex-1 items-center justify-center bg-black/40 px-4 py-4">
          <View
            className="max-h-[85vh] w-full max-w-md rounded-2xl bg-card p-4"
            style={{ borderRadius: 16, overflow: 'visible' }}
          >
            <View className="gap-1 pb-4">
              <Text className="text-lg font-bold text-app">Новый {KIND_LABEL[draft.kind].toLowerCase()}</Text>
              <Text className="text-xs text-muted">
                {draft.levelLabel} · {draft.columnLabel}
                {draft.laneTitle ? ` · ${draft.laneTitle}` : ''}
              </Text>
            </View>

            <ScrollView
              className="min-h-0 flex-1"
              contentContainerClassName="gap-4 px-0.5 pb-4"
              keyboardShouldPersistTaps="handled"
              automaticallyAdjustKeyboardInsets
            >
              <TextField
                label="Название"
                value={title}
                onChangeText={(value) => {
                  setTitle(value);
                  setTitleError(null);
                  clearFormError();
                }}
                error={titleError}
                placeholder="Например: Запуск новой ветки"
                autoFocus
              />

              <TextField
                label="Кодовое название (необязательно)"
                value={slug}
                onChangeText={(value) => {
                  setSlug(value);
                  clearFormError();
                }}
                placeholder="Например: LAUNCH"
                maxLength={SLUG_MAX_LENGTH}
                autoCapitalize="none"
                autoCorrect={false}
                // На web браузер любит подставлять в незнакомое поле сохранённое
                // значение (в поле кодового названия так появлялись слова вроде
                // «empty»). Метку вводит только человек, поэтому автозаполнение
                // отключено и для полей формы, и для нативной клавиатуры.
                autoComplete="off"
                textContentType="none"
              />

              <TextField
                label="Описание"
                value={description}
                onChangeText={(value) => {
                  setDescription(value);
                  clearFormError();
                }}
                placeholder="Что нужно сделать и зачем (необязательно)"
                multiline
                numberOfLines={4}
                maxLength={DESCRIPTION_MAX_LENGTH}
              />

              <DatePickerField
                label="Дедлайн (необязательно)"
                value={deadline}
                onChange={(value) => {
                  setDeadline(value);
                  clearFormError();
                }}
                placeholder="Без срока"
              />

              {/* Приоритет — свойство самой карточки: выбор есть всегда, в любой
                  колонке и на любом уровне. Переключатель компактный: три
                  значения умещаются в строку, а длинные подписи-пояснения здесь
                  не нужны — «Низкий» / «Средний» / «Высокий» говорят сами за
                  себя. По умолчанию выбран средний (DEFAULT_CARD_PRIORITY). */}
              <View className="gap-1.5">
                <Text className="text-sm font-medium text-app">Приоритет</Text>

                <SegmentedSwitch
                  options={PRIORITIES.map((item) => ({ value: item.id, label: item.label }))}
                  value={priority}
                  onChange={(value) => {
                    setPriority(value);
                    clearFormError();
                  }}
                />
              </View>

              {stageSelectable ? (
                <View className="gap-1.5">
                  <Text className="text-sm font-medium text-app">Степень проработки</Text>

                  <StageOption
                    label={WORK_STAGE_LABEL.idea}
                    hint="Карточка ещё не готова к работе: её уточняют, прежде чем брать в работу."
                    selected={idea}
                    onPress={() => {
                      setIdea(true);
                      clearFormError();
                    }}
                  />

                  <StageOption
                    label={WORK_STAGE_LABEL.ready}
                    hint="Карточка продумана: её можно брать в работу."
                    selected={!idea}
                    onPress={() => {
                      setIdea(false);
                      clearFormError();
                    }}
                  />
                </View>
              ) : null}

              {parentOptions !== null ? (
                <View className="gap-1.5">
                  <Text className="text-sm font-medium text-app">{PARENT_LABEL[draft.kind]}</Text>

                  <ScrollView className="max-h-48" contentContainerClassName="gap-1.5">
                    {draft.allowNoParent ? (
                      <ParentOption
                        label={draft.noParentLabel}
                        color={UNPARENTED_CARD_COLOR}
                        selected={parentId === null}
                        onPress={() => {
                          setParentId(null);
                        }}
                      />
                    ) : null}

                    {parentOptions.map((option) => (
                      <ParentOption
                        key={option.id}
                        label={option.title}
                        color={option.color}
                        selected={parentId === option.id}
                        onPress={() => {
                          setParentId(option.id);
                        }}
                      />
                    ))}
                  </ScrollView>

                  {/* Причина, по которой выбрать родителя нельзя, всплывает
                      тостом (см. `parentBlockReason` выше). */}
                </View>
              ) : null}

              <View className="flex-row items-center gap-2">
                <Text className="text-xs text-subtle">Цвет карточки</Text>

                <View
                  className="h-4 w-4 rounded-full border border-border"
                  style={{ backgroundColor: directionColorHex(previewColor) }}
                />

                <Text className="text-xs text-muted">{previewColor === null ? 'без родителя' : 'наследуется'}</Text>
              </View>
            </ScrollView>

            <View className="flex-row gap-2">
              <Pressable
                accessibilityRole="button"
                onPress={close}
                className="h-11 flex-1 items-center justify-center rounded-xl bg-surface active:opacity-80"
              >
                <Text className="text-sm font-semibold text-app">Отмена</Text>
              </Pressable>

              <Pressable
                accessibilityRole="button"
                accessibilityState={{ disabled: createDisabled }}
                disabled={createDisabled}
                onPress={submit}
                className={[
                  'h-11 flex-1 items-center justify-center rounded-xl bg-app active:opacity-90',
                  createDisabled ? 'opacity-60' : '',
                ].join(' ')}
              >
                <Text className="text-sm font-semibold text-white">Создать</Text>
              </Pressable>
            </View>
          </View>
        </View>
      </KeyboardAvoidingView>
    </Modal>
  );
}
