/**
 * Модалка редактирования карточки.
 *
 * Открывается кликом по карточке. Редактируются название, описание и статус
 * (колонка доски), карточку можно удалить. Доступные статусы — `allowedTransitions`
 * из ответа сервера: если у карточки есть дети в работе, смена статуса
 * запрещена, статусы в списке заблокированы и причина показана под ними.
 *
 * Степень проработки («Идея» / «На реализацию») правится только у карточки
 * из беклога: у карточки в работе или в закрытой колонке выбора нет, а флаг
 * в данных сохраняется — он снова станет актуальным, если карточку вернуть
 * в беклог. Поэтому `idea` отправляется, только когда его выбрали в форме.
 *
 * Приоритет, в отличие от степени проработки, правится всегда: он не зависит
 * ни от колонки, ни от уровня. Как и описание, приоритет приходит отдельным
 * запросом, поэтому до его ответа в поле стоит «не загружено» и PATCH его
 * не отправляет — иначе сохранение стёрло бы приоритет, которого мы не видели.
 *
 * Описания в списке карточек нет, поэтому при каждом открытии модалка
 * запрашивает карточку отдельно (`GET /tasks/{task}`) и показывает общий
 * лоадер на всю форму, пока данные не придут: поля не должны появляться
 * пустыми и перезаписываться, пока пользователь уже начал в них печатать.
 */

import { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Modal,
  Platform,
  Pressable,
  ScrollView,
  Text,
  View,
} from 'react-native';

import { fetchTask } from '../../api/client';
import { getToken } from '../../auth/session';
import { SegmentedSwitch } from '../../components/SegmentedSwitch';
import { TextField } from '../../components/TextField';
import { useFormErrorToast } from '../../notifications/useFormErrorToast';
import { DatePickerField } from './DatePickerField';
import { directionColorHex } from '../directionColors';
import { toDeadlineDate } from '../deadline';
import { toCardDescription, toCardSlug } from '../mappers';
import {
  COLUMNS,
  DEFAULT_CARD_PRIORITY,
  DESCRIPTION_MAX_LENGTH,
  KIND_LABEL,
  PRIORITIES,
  SLUG_MAX_LENGTH,
  WORK_STAGE_LABEL,
  type Card,
  type CardPriority,
  type ColumnId,
} from '../types';

interface CardEditModalProps {
  /** Редактируемая карточка; null — модалка закрыта. */
  card: Card | null;
  /** Причина, по которой сменить статус нельзя; null — смена разрешена. */
  lockedReason: string | null;
  /**
   * Сохранить изменения; возвращает текст ошибки, который показываем в модалке.
   * `description` — null, если описание ещё не загружено: поле тогда не отправляется.
   * `slug` — кодовое название: null очищает метку, undefined — не отправлять
   * (ещё не загружено).
   * `deadline` — дата в формате API (`YYYY-MM-DD`) или null; undefined — не отправлять.
   * `idea` — степень проработки: undefined — не отправлять (выбор не показывался).
   * `priority` — приоритет карточки: undefined — не отправлять (ещё не загружен).
   */
  onSave: (
    card: Card,
    title: string,
    slug: string | null | undefined,
    description: string | null,
    column: ColumnId,
    deadline: string | null | undefined,
    idea: boolean | undefined,
    priority: CardPriority | undefined,
  ) => Promise<string | null>;
  /** Удалить карточку; возвращает текст ошибки сервера (409 — есть дочерние карточки). */
  onDelete: (card: Card) => Promise<string | null>;
  /**
   * Режим «только чтение»: карточка открывается на просмотр.
   *
   * Поля остаются видимыми, но кнопок «Сохранить» и «Удалить» нет: сервер
   * отклонил бы запись (403), поэтому форма не обещает невыполнимого.
   */
  readOnly?: boolean;
  onClose: () => void;
}

/**
 * Модалка с формой редактирования.
 *
 * Экран монтирует её заново на каждое открытие (`key` по id карточки), поэтому
 * описание грузится один раз при монтировании и привязано к конкретной карточке.
 */
export function CardEditModal(props: CardEditModalProps) {
  if (props.card === null) {
    return null;
  }

  return (
    <CardEditForm
      key={props.card.id}
      card={props.card}
      lockedReason={props.lockedReason}
      readOnly={props.readOnly ?? false}
      onSave={props.onSave}
      onDelete={props.onDelete}
      onClose={props.onClose}
    />
  );
}

interface CardEditFormProps {
  card: Card;
  lockedReason: string | null;
  readOnly: boolean;
  onSave: (
    card: Card,
    title: string,
    slug: string | null | undefined,
    description: string | null,
    column: ColumnId,
    deadline: string | null | undefined,
    idea: boolean | undefined,
    priority: CardPriority | undefined,
  ) => Promise<string | null>;
  onDelete: (card: Card) => Promise<string | null>;
  onClose: () => void;
}

interface StatusOptionProps {
  label: string;
  selected: boolean;
  locked: boolean;
  onPress: () => void;
}

function StatusOption({ label, selected, locked, onPress }: StatusOptionProps) {
  return (
    <Pressable
      accessibilityRole="radio"
      accessibilityState={{ selected, disabled: locked }}
      disabled={locked}
      onPress={onPress}
      className={[
        'flex-row items-center gap-2 rounded-xl border px-3 py-2',
        selected ? 'border-app bg-surface' : 'border-border',
        locked ? 'opacity-50' : '',
      ].join(' ')}
    >
      <Text className="flex-1 text-sm text-app">{label}</Text>

      {selected ? <Text className="text-xs font-semibold text-app">✓</Text> : null}
    </Pressable>
  );
}

function CardEditForm({ card, lockedReason, readOnly, onSave, onDelete, onClose }: CardEditFormProps) {
  const [title, setTitle] = useState(card.title);
  /**
   * Кодовое название из полного представления — undefined, пока не загружено.
   * Как и описание: незагруженное значение не отправляется, иначе PATCH
   * стёр бы метку, которую мы ещё не видели.
   */
  const [slug, setSlug] = useState<string | null | undefined>(undefined);
  /** Описание из полного представления карточки; null — ещё не загружено. */
  const [description, setDescription] = useState<string | null>(null);
  /**
   * Дедлайн в формате API (`YYYY-MM-DD`); undefined — ещё не загружен.
   * Как и описание: незагруженное значение не отправляется, иначе PATCH
   * стёр бы срок, которого мы ещё не видели.
   */
  const [deadline, setDeadline] = useState<string | null | undefined>(undefined);
  const [columnId, setColumnId] = useState<ColumnId>(card.column);
  /**
   * Степень проработки из полного представления; undefined — ещё не загружена.
   * Как и описание: незагруженное значение не отправляется, иначе PATCH
   * перезаписал бы флаг, которого мы ещё не видели.
   */
  const [idea, setIdea] = useState<boolean | undefined>(undefined);
  /**
   * Приоритет из полного представления; undefined — ещё не загружен.
   * Правило то же, что у описания: незагруженное значение не отправляется,
   * иначе PATCH перезаписал бы приоритет, которого мы ещё не видели.
   */
  const [priority, setPriority] = useState<CardPriority | undefined>(undefined);
  const [titleError, setTitleError] = useState<string | null>(null);
  /**
   * Ошибка сохранения (например, запрет сервера) показывается тостом — единым
   * стилем со всеми ошибками доски. Правка любого поля её снимает: причина
   * отказа чаще всего в самих данных.
   */
  const { show: showSaveError, clear: clearSaveError } = useFormErrorToast({
    formKey: `card-edit-${card.id}`,
    title: 'Не удалось сохранить карточку',
  });
  /**
   * Ошибка удаления — отдельный слот и заголовок: причина отказа (409 — есть
   * дочерние карточки) от полей формы не зависит, поэтому правка названия или
   * статуса не должна её гасить.
   */
  const { show: showDeleteError, clear: clearDeleteError } = useFormErrorToast({
    formKey: `card-edit-${card.id}`,
    title: 'Не удалось удалить карточку',
  });
  const [saving, setSaving] = useState(false);
  // Удаление подтверждается в самой модалке: на web системные диалоги не работают.
  const [confirmingDelete, setConfirmingDelete] = useState(false);
  /** Пока описание грузится, вместо полей показывается общий лоадер. */
  const [loading, setLoading] = useState(true);
  /** Ошибка загрузки описания: без неё поля показывать нельзя — сохраним пустым. */
  const [loadError, setLoadError] = useState<string | null>(null);
  /**
   * Причина неудачной загрузки показывается тостом: в самой форме остаётся
   * только действие («Повторить»/«Закрыть»), текст ошибки не занимает тело.
   */
  const { show: showLoadError, clear: clearLoadError } = useFormErrorToast({
    formKey: `card-edit-${card.id}`,
    title: 'Не удалось загрузить карточку',
  });
  /** Счётчик повторов: изменение значения перезапускает эффект загрузки. */
  const [loadAttempt, setLoadAttempt] = useState(0);
  /**
   * Загрузить карточку целиком ради описания.
   *
   * Вызывается эффектом при монтировании и кнопкой «Повторить». Состояние
   * «загружается» ставится здесь, а не в теле эффекта: в теле это дало бы
   * каскадный рендер при каждом открытии модалки.
   */
  const loadCard = useCallback(async (): Promise<void> => {
    try {
      const detail = await fetchTask(getToken(), card.id);

      setDescription(toCardDescription(detail));
      setSlug(toCardSlug(detail));
      setDeadline(toDeadlineDate(detail.deadline));
      setIdea(detail.idea === true);
      setPriority(detail.priority ?? DEFAULT_CARD_PRIORITY);
      setTitle(detail.title);
      setLoadError(null);
      clearLoadError();
    } catch {
      setLoadError('Не удалось загрузить карточку. Проверьте соединение и попробуйте снова.');
      // Причина — тостом: в форме остаётся только кнопка «Повторить».
      showLoadError('Проверьте соединение с сервером и попробуйте снова.');
    } finally {
      setLoading(false);
    }
  }, [card.id, showLoadError, clearLoadError]);

  useEffect(() => {
    // Загрузка — асинхронный внешний вызов: состояние обновится после ответа API,
    // а не синхронным setState внутри эффекта.
    const request = Promise.resolve().then(() => loadCard());

    void request;
  }, [loadCard, loadAttempt]);

  /** Повторить неудачную загрузку: сначала снова показываем лоадер. */
  const retryLoad = () => {
    clearLoadError();
    setLoading(true);
    setLoadError(null);
    setLoadAttempt((attempt) => attempt + 1);
  };

  const submit = async () => {
    const trimmed = title.trim();

    if (trimmed === '') {
      setTitleError('Укажите название.');
      return;
    }

    const descriptionValue = description?.trim() ?? '';
    // Незагруженное описание (null) не отправляем вовсе: иначе PATCH стёр бы
    // текст карточки, которого мы ещё не видели. Стёртое описание — пустая
    // строка, сервер нормализует её в null.
    const nextDescription = description === null ? null : descriptionValue;

    // Кодовое название — по тому же правилу: undefined не отправляем, а
    // стёртая метка уходит пустой строкой и сервер записывает null.
    const nextSlug = slug === undefined ? undefined : (slug ?? '').trim();

    // Степень проработки отправляем, только если выбор был доступен (карточка
    // из беклога) и значение уже загружено: иначе PATCH стёр бы флаг.
    const nextIdea = ideaSelectable ? idea : undefined;

    setSaving(true);
    const error = await onSave(card, trimmed, nextSlug, nextDescription, columnId, deadline, nextIdea, priority);
    setSaving(false);

    if (error !== null) {
      showSaveError(error);
    }
  };

  /**
   * Удалить карточку.
   *
   * Причину отказа (409 — есть дочерние карточки) показывает тост: он всплывает
   * поверх попапа, поэтому сообщение видно, даже если кнопка удаления внизу
   * прокручиваемой формы. Правка полей его не снимает — отказ от них не зависит.
   * `finally` возвращает кнопки в рабочее состояние, даже если действие упадёт
   * исключением: иначе форма осталась бы навсегда заблокированной (`saving`).
   */
  const remove = async () => {
    setSaving(true);
    clearDeleteError();

    try {
      const error = await onDelete(card);

      if (error !== null) {
        showDeleteError(error);
        // Подтверждение прячем: причина отказа уже показана, а повторное
        // действие начинается заново.
        setConfirmingDelete(false);
      }
    } finally {
      setSaving(false);
    }
  };

  /** Открыть подтверждение удаления: прошлая причина отказа уже не актуальна. */
  const askDeleteConfirmation = () => {
    clearDeleteError();
    setConfirmingDelete(true);
  };

  const titleEmpty = title.trim() === '';
  /**
   * Выбор степени проработки доступен только у карточки из беклога — по её
   * текущей колонке, а не по выбранной в форме: перевели на «В работе» и выбор
   * пропадает, а сохранённое значение флага остаётся в данных.
   */
  const ideaSelectable = card.column === 'backlog';

  return (
    <Modal visible transparent animationType="fade" onRequestClose={onClose}>
      {/* Форма длинная (описание, дедлайн, статусы, удаление) и на низком экране
          не помещается целиком, поэтому карточка ограничена высотой экрана:
          шапка и кнопки закреплены, прокручивается только тело.
          KeyboardAvoidingView поднимает форму над клавиатурой на iOS.
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
              <Text className="text-lg font-bold text-app">Карточка</Text>
              <Text className="text-xs text-muted">{KIND_LABEL[card.kind]}</Text>
              {/* Статус учётной записи важнее подписи типа: по нему понятно,
                  почему в форме нет кнопок сохранения и удаления. */}
              {readOnly ? <Text className="text-xs font-semibold text-amber-800">Режим только для чтения</Text> : null}
            </View>

            {loading || loadError !== null ? (
              // Лоадер и ошибку загрузки прокручивать незачем, но они живут в той же
              // области тела: футер с «Отмена/Сохранить» показывается только для формы.
              <ScrollView className="min-h-0 flex-1" contentContainerClassName="gap-4 px-0.5 pb-4">
                {loading ? (
                  // Описание приходит отдельным запросом: форму показываем целиком и
                  // сразу готовой к правке, а не с пустым полем, которое вот-вот
                  // перезапишется ответом сервера.
                  <View className="items-center gap-3 py-8">
                    <ActivityIndicator size="large" />
                    <Text className="text-sm text-muted">Загружаем карточку…</Text>
                  </View>
                ) : (
                  <View className="items-center gap-3 py-6">
                    {/* Причину неудачной загрузки показывает тост (см. `loadCard`), здесь
                        остаётся только действие: повторить или закрыть. */}
                    <Text className="text-sm text-muted">Карточка не загрузилась</Text>

                    <View className="w-full flex-row gap-2">
                      <Pressable
                        accessibilityRole="button"
                        onPress={onClose}
                        className="h-10 flex-1 items-center justify-center rounded-xl bg-surface active:opacity-80"
                      >
                        <Text className="text-sm font-semibold text-app">Закрыть</Text>
                      </Pressable>

                      <Pressable
                        accessibilityRole="button"
                        onPress={retryLoad}
                        className="h-10 flex-1 items-center justify-center rounded-xl bg-app active:opacity-90"
                      >
                        <Text className="text-sm font-semibold text-white">Повторить</Text>
                      </Pressable>
                    </View>
                  </View>
                )}
              </ScrollView>
            ) : (
              <>
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
                      clearSaveError();
                    }}
                    error={titleError}
                    placeholder="Например: Запуск новой ветки"
                  />

                  <TextField
                    label="Кодовое название (необязательно)"
                    value={slug ?? ''}
                    onChangeText={(value) => {
                      setSlug(value);
                      clearSaveError();
                    }}
                    placeholder="Например: LAUNCH"
                    maxLength={SLUG_MAX_LENGTH}
                    autoCapitalize="none"
                    autoCorrect={false}
                    // Как и в форме создания: метку вводит человек, автозаполнение
                    // браузера в это поле подставляло случайные слова.
                    autoComplete="off"
                    textContentType="none"
                  />

                  <TextField
                    label="Описание"
                    value={description ?? ''}
                    onChangeText={(value) => {
                      setDescription(value);
                      clearSaveError();
                    }}
                    placeholder="Что нужно сделать и зачем (необязательно)"
                    multiline
                    numberOfLines={4}
                    maxLength={DESCRIPTION_MAX_LENGTH}
                  />

                  <DatePickerField
                    label="Дедлайн (необязательно)"
                    value={deadline ?? null}
                    onChange={(value) => {
                      setDeadline(value);
                      clearSaveError();
                    }}
                    placeholder="Без срока"
                  />

                  {/* Приоритет правится у карточки в любой колонке: он не
                      привязан к этапу работы. Пустое значение до загрузки
                      карточки не показываем — вместо формы в этот момент
                      рисуется лоадер (см. `loading`), поэтому сюда попадает
                      уже загруженный приоритет. */}
                  <View className="gap-1.5">
                    <Text className="text-sm font-medium text-app">Приоритет</Text>

                    <SegmentedSwitch
                      options={PRIORITIES.map((item) => ({ value: item.id, label: item.label }))}
                      value={priority ?? DEFAULT_CARD_PRIORITY}
                      onChange={(value) => {
                        setPriority(value);
                        clearSaveError();
                      }}
                    />
                  </View>

                  {ideaSelectable ? (
                    <View className="gap-1.5">
                      <Text className="text-sm font-medium text-app">Степень проработки</Text>

                      <View className="gap-1.5">
                        <StatusOption
                          label={WORK_STAGE_LABEL.idea}
                          selected={idea === true}
                          locked={saving}
                          onPress={() => {
                            setIdea(true);
                            clearSaveError();
                          }}
                        />

                        <StatusOption
                          label={WORK_STAGE_LABEL.ready}
                          selected={idea === false}
                          locked={saving}
                          onPress={() => {
                            setIdea(false);
                            clearSaveError();
                          }}
                        />
                      </View>
                    </View>
                  ) : null}

                  <View className="gap-1.5">
                    <Text className="text-sm font-medium text-app">Статус</Text>

                    <View className="gap-1.5">
                      {COLUMNS.map((option) => (
                        <StatusOption
                          key={option.id}
                          label={option.label}
                          selected={option.id === columnId}
                          // Заблокированы статусы, которые сервер не разрешил, — и всегда
                          // после начала запроса, чтобы не отправлять второй раз.
                          // В режиме «только чтение» выбор недоступен вовсе.
                          locked={readOnly || saving || !card.allowedTransitions.includes(option.id)}
                          onPress={() => {
                            setColumnId(option.id);
                            clearSaveError();
                          }}
                        />
                      ))}
                    </View>

                    {/* Причина заблокированных статусов считается на клиенте по
                        разрешённым переходам, поэтому показывается подписью под
                        блоком статусов, а не тостом: это постоянная блокировка. */}
                    {lockedReason === null ? null : <Text className="text-xs text-err-fg">{lockedReason}</Text>}
                  </View>

                  <View className="flex-row items-center gap-2">
                    <Text className="text-xs text-subtle">Цвет карточки</Text>

                    <View
                      className="h-4 w-4 rounded-full border border-border"
                      style={{ backgroundColor: directionColorHex(card.color) }}
                    />
                  </View>

                  {/* Удаление живёт в конце прокручиваемого тела. Причину отказа
                      сервера (409 — есть дочерние карточки) показывает тост: он
                      всплывает поверх попапа, поэтому сообщение видно, даже если
                      кнопка удаления внизу формы (см. `remove`).
                      В режиме «только чтение» блока нет: удаление запрещено. */}
                  {readOnly ? (
                    <Text className="text-xs text-muted">
                      Режим только для чтения: данные карточки можно только просматривать.
                    </Text>
                  ) : (
                    <View className="gap-2">
                      {confirmingDelete ? (
                        <View className="gap-2 rounded-xl border border-border bg-surface p-3">
                          <Text className="text-sm font-medium text-app">Удалить карточку «{card.title}»?</Text>

                          <View className="flex-row gap-2">
                            <Pressable
                              accessibilityRole="button"
                              onPress={() => setConfirmingDelete(false)}
                              className="h-10 flex-1 items-center justify-center rounded-xl bg-card active:opacity-80"
                            >
                              <Text className="text-sm font-semibold text-app">Оставить</Text>
                            </Pressable>

                            <Pressable
                              accessibilityRole="button"
                              accessibilityState={{ disabled: saving, busy: saving }}
                              disabled={saving}
                              onPress={() => void remove()}
                              className={[
                                'h-10 flex-1 flex-row items-center justify-center gap-2 rounded-xl bg-err-fg active:opacity-90',
                                saving ? 'opacity-60' : '',
                              ].join(' ')}
                            >
                              {/* Запрос идёт до 15 секунд (API_TIMEOUT_MS): спиннер
                                показывает, что нажатие принято, и удерживает от
                                повторного клика. */}
                              {saving ? <ActivityIndicator size="small" color="#fff" /> : null}

                              <Text className="text-sm font-semibold text-white">
                                {saving ? 'Удаляем…' : 'Удалить'}
                              </Text>
                            </Pressable>
                          </View>
                        </View>
                      ) : (
                        <Pressable
                          accessibilityRole="button"
                          accessibilityState={{ disabled: saving }}
                          disabled={saving}
                          onPress={askDeleteConfirmation}
                          className="h-10 items-center justify-center rounded-xl border border-border active:bg-surface"
                        >
                          <Text className="text-sm font-semibold text-err-fg">Удалить карточку</Text>
                        </Pressable>
                      )}
                    </View>
                  )}
                </ScrollView>

                <View className="flex-row gap-2">
                  <Pressable
                    accessibilityRole="button"
                    onPress={onClose}
                    className="h-11 flex-1 items-center justify-center rounded-xl bg-surface active:opacity-80"
                  >
                    <Text className="text-sm font-semibold text-app">{readOnly ? 'Закрыть' : 'Отмена'}</Text>
                  </Pressable>

                  {/* В режиме «только чтение» сохранять нечего: кнопки нет,
                      поля остаются для просмотра. */}
                  {readOnly ? null : (
                    <Pressable
                      accessibilityRole="button"
                      accessibilityState={{ disabled: titleEmpty || saving }}
                      disabled={titleEmpty || saving}
                      onPress={() => void submit()}
                      className={[
                        'h-11 flex-1 items-center justify-center rounded-xl bg-app active:opacity-90',
                        titleEmpty || saving ? 'opacity-60' : '',
                      ].join(' ')}
                    >
                      <Text className="text-sm font-semibold text-white">Сохранить</Text>
                    </Pressable>
                  )}
                </View>
              </>
            )}
          </View>
        </View>
      </KeyboardAvoidingView>
    </Modal>
  );
}
