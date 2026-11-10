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
import { FormAlert } from '../../components/FormAlert';
import { TextField } from '../../components/TextField';
import { DatePickerField } from './DatePickerField';
import { directionColorHex } from '../directionColors';
import { toDeadlineDate } from '../deadline';
import { toCardDescription, toCardSlug } from '../mappers';
import {
  COLUMNS,
  DESCRIPTION_MAX_LENGTH,
  KIND_LABEL,
  SLUG_MAX_LENGTH,
  WORK_STAGE_LABEL,
  type Card,
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
   */
  onSave: (
    card: Card,
    title: string,
    slug: string | null | undefined,
    description: string | null,
    column: ColumnId,
    deadline: string | null | undefined,
    idea: boolean | undefined,
  ) => Promise<string | null>;
  /** Удалить карточку; возвращает текст ошибки сервера (409 — есть дочерние карточки). */
  onDelete: (card: Card) => Promise<string | null>;
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
      onSave={props.onSave}
      onDelete={props.onDelete}
      onClose={props.onClose}
    />
  );
}

interface CardEditFormProps {
  card: Card;
  lockedReason: string | null;
  onSave: (
    card: Card,
    title: string,
    slug: string | null | undefined,
    description: string | null,
    column: ColumnId,
    deadline: string | null | undefined,
    idea: boolean | undefined,
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

function CardEditForm({ card, lockedReason, onSave, onDelete, onClose }: CardEditFormProps) {
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
  const [titleError, setTitleError] = useState<string | null>(null);
  // Ошибки сохранения и удаления (например, запрет сервера) показываем здесь же.
  const [formError, setFormError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  // Удаление подтверждается в самой модалке: на web системные диалоги не работают.
  const [confirmingDelete, setConfirmingDelete] = useState(false);
  /** Пока описание грузится, вместо полей показывается общий лоадер. */
  const [loading, setLoading] = useState(true);
  /** Ошибка загрузки описания: без неё поля показывать нельзя — сохраним пустым. */
  const [loadError, setLoadError] = useState<string | null>(null);
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
      setTitle(detail.title);
      setLoadError(null);
    } catch {
      setLoadError('Не удалось загрузить карточку. Проверьте соединение и попробуйте снова.');
    } finally {
      setLoading(false);
    }
  }, [card.id]);

  useEffect(() => {
    // Загрузка — асинхронный внешний вызов: состояние обновится после ответа API,
    // а не синхронным setState внутри эффекта.
    const request = Promise.resolve().then(() => loadCard());

    void request;
  }, [loadCard, loadAttempt]);

  /** Повторить неудачную загрузку: сначала снова показываем лоадер. */
  const retryLoad = () => {
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
    const error = await onSave(card, trimmed, nextSlug, nextDescription, columnId, deadline, nextIdea);
    setSaving(false);

    if (error !== null) {
      setFormError(error);
    }
  };

  const remove = async () => {
    setSaving(true);
    const error = await onDelete(card);
    setSaving(false);

    if (error !== null) {
      setFormError(error);
      setConfirmingDelete(false);
    }
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
          KeyboardAvoidingView поднимает форму над клавиатурой на iOS. */}
      <KeyboardAvoidingView className="flex-1" behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <View className="flex-1 items-center justify-center bg-black/40 px-4 py-4">
          <View className="max-h-[85vh] w-full max-w-md overflow-hidden rounded-2xl bg-card p-5">
            <View className="gap-1 pb-4">
              <Text className="text-lg font-bold text-app">Карточка</Text>
              <Text className="text-xs text-muted">{KIND_LABEL[card.kind]}</Text>
            </View>

            {loading || loadError !== null ? (
              // Лоадер и ошибку загрузки прокручивать незачем, но они живут в той же
              // области тела: футер с «Отмена/Сохранить» показывается только для формы.
              <ScrollView className="min-h-0 flex-1" contentContainerClassName="gap-4 pb-4">
                {loading ? (
                  // Описание приходит отдельным запросом: форму показываем целиком и
                  // сразу готовой к правке, а не с пустым полем, которое вот-вот
                  // перезапишется ответом сервера.
                  <View className="items-center gap-3 py-8">
                    <ActivityIndicator size="large" />
                    <Text className="text-sm text-muted">Загружаем карточку…</Text>
                  </View>
                ) : (
                  <View className="gap-2">
                    <FormAlert message={loadError ?? ''} />

                    <View className="flex-row gap-2">
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
                  contentContainerClassName="gap-4 pb-4"
                  keyboardShouldPersistTaps="handled"
                  automaticallyAdjustKeyboardInsets
                >
                  {formError ? <FormAlert message={formError} /> : null}

                  <TextField
                    label="Название"
                    value={title}
                    onChangeText={(value) => {
                      setTitle(value);
                      setTitleError(null);
                      setFormError(null);
                    }}
                    error={titleError}
                    placeholder="Например: Запуск новой ветки"
                  />

                  <TextField
                    label="Кодовое название (необязательно)"
                    value={slug ?? ''}
                    onChangeText={(value) => {
                      setSlug(value);
                      setFormError(null);
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
                      setFormError(null);
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
                      setFormError(null);
                    }}
                    placeholder="Без срока"
                  />

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
                            setFormError(null);
                          }}
                        />

                        <StatusOption
                          label={WORK_STAGE_LABEL.ready}
                          selected={idea === false}
                          locked={saving}
                          onPress={() => {
                            setIdea(false);
                            setFormError(null);
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
                          locked={saving || !card.allowedTransitions.includes(option.id)}
                          onPress={() => {
                            setColumnId(option.id);
                            setFormError(null);
                          }}
                        />
                      ))}
                    </View>

                    {lockedReason ? <Text className="text-xs text-err-fg">{lockedReason}</Text> : null}
                  </View>

                  <View className="flex-row items-center gap-2">
                    <Text className="text-xs text-subtle">Цвет карточки</Text>

                    <View
                      className="h-4 w-4 rounded-full border border-border"
                      style={{ backgroundColor: directionColorHex(card.color) }}
                    />
                  </View>

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
                          accessibilityState={{ disabled: saving }}
                          disabled={saving}
                          onPress={() => void remove()}
                          className={[
                            'h-10 flex-1 items-center justify-center rounded-xl bg-err-fg active:opacity-90',
                            saving ? 'opacity-60' : '',
                          ].join(' ')}
                        >
                          <Text className="text-sm font-semibold text-white">Удалить</Text>
                        </Pressable>
                      </View>
                    </View>
                  ) : (
                    <Pressable
                      accessibilityRole="button"
                      accessibilityState={{ disabled: saving }}
                      disabled={saving}
                      onPress={() => setConfirmingDelete(true)}
                      className="h-10 items-center justify-center rounded-xl border border-border active:bg-surface"
                    >
                      <Text className="text-sm font-semibold text-err-fg">Удалить карточку</Text>
                    </Pressable>
                  )}
                </ScrollView>

                <View className="flex-row gap-2">
                  <Pressable
                    accessibilityRole="button"
                    onPress={onClose}
                    className="h-11 flex-1 items-center justify-center rounded-xl bg-surface active:opacity-80"
                  >
                    <Text className="text-sm font-semibold text-app">Отмена</Text>
                  </Pressable>

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
                </View>
              </>
            )}
          </View>
        </View>
      </KeyboardAvoidingView>
    </Modal>
  );
}
