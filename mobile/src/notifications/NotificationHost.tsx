/**
 * Слой всплывающих уведомлений.
 *
 * Рисуется поверх всего приложения: монтируется в `app/_layout.tsx` внутри
 * `NotificationProvider`, поэтому сообщение видно и над доской, и над открытым
 * попапом карточки или направлений.
 *
 * Почему `Modal`, а не `position: absolute` в корне: на native модалка — отдельный
 * нативный слой, нет проблемы перекрытия другими модалками и обрезки `ScrollView`;
 * на web react-native-web рендерит её как `position: fixed`, то есть плашка не
 * уезжает при прокрутке страницы. Затемнения у модалки нет (`transparent`), и
 * кликам она не мешает: подложка прозрачна для нажатий (`pointerEvents="box-none"`),
 * а плашка включает их обратно (`pointerEvents="auto"`).
 *
 * Почему `pointerEvents` — проп, а не класс `pointer-events-none/auto`: на web
 * NativeWind 4 передаёт `className` в react-native-web как `style`, а тот
 * игнорирует CSS-свойство `pointer-events` в inline-стиле (он умеет только проп
 * `pointerEvents`, который компилируется в CSS-класс с `!important`). Класс
 * молча не работал: подложка модалки забирала клики у доски, а плашки не
 * реагировали ни на нажатие, ни на крестик.
 *
 * Позиция зависит от ширины экрана:
 *  - узкий экран (телефон) — полоса сверху во всю ширину: справа места нет;
 *  - широкий экран — правый верхний угол: доска не перекрывается целиком.
 * Сверху оставлено место под статус-бар и хедер стека, чтобы плашка не уходила
 * под строку заголовка.
 */

import { useEffect, useState } from 'react';
import { Animated, Easing, Modal, Platform, Pressable, Text, View, useWindowDimensions } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { AlertCircle, CircleCheck, Info, TriangleAlert, X, type LucideIcon } from 'lucide-react-native';

import { useNotifications, type AppNotification, type NotificationKind } from './NotificationContext';

/** Ниже этой ширины уведомление растягивается по всей ширине сверху (телефон). */
const COMPACT_MAX_WIDTH = 640;

/**
 * На сколько контейнер модалки опускается в стек наложения под тостами.
 *
 * На native модалка — отдельное нативное окно, и открытый позже попап всегда
 * оказывается выше тостов, сколько бы `z-index` мы ни задали: порядок задаёт
 * нативный слой, а не стиль. Поэтому тост живёт внутри модалки на одну ступень
 * выше (`TOAST_LAYER` ниже): открывшаяся позже модалка ставит в стек свой
 * контейнер, тост поднимает свой — и содержимое попапа остаётся под плашкой.
 */
const TOAST_LAYER = 1000;

/**
 * Стиль контейнера модалки, внутри которой может висеть тост.
 *
 * Модалки (карточка, направления, меню) передают его в `style` своего `Modal`.
 * На native иначе тост оказывается под только что открытым попапом: собственный
 * контейнер модалки перекрывает тот, где живут тосты. На web модалки и так
 * позиционированы, и порядок решает порядок монтирования — там стиль пустой.
 *
 * `zIndex`, а не класс: `Modal` не разбирает `className`, а на web
 * react-native-web игнорирует CSS-свойства, пришедшие внутри `style`.
 */
export const TOAST_MODAL_STYLE = Platform.OS === 'web' ? undefined : { zIndex: TOAST_LAYER };

/** Отступ плашки от краёв экрана на широкой раскладке. */
const SCREEN_MARGIN = 12;

/** Ширина плашки на широком экране: уже попапов, чтобы не закрывать доску. */
const WIDE_TOAST_WIDTH = 384;

/**
 * Запас сверху под хедер стека.
 *
 * Точной высоты хедера в коде нет (она различается по платформам), поэтому к
 * верхнему safe-area отступу добавляется запас на строку заголовка: лучше
 * опустить плашку чуть ниже, чем спрятать её под шапкой.
 */
const HEADER_RESERVE = 56;

/** Оформление по виду уведомления: рамка, фон, цвет текста, иконка. */
interface KindStyle {
  container: string;
  title: string;
  message: string;
  icon: LucideIcon;
  iconColor: string;
}

const KIND_STYLES: Record<NotificationKind, KindStyle> = {
  // Ошибка — те же токены, что у FormAlert и кнопок удаления.
  error: {
    container: 'border-err-fg bg-err-bg',
    title: 'text-err-fg',
    message: 'text-err-fg',
    icon: AlertCircle,
    iconColor: '#991b1b',
  },
  // Предупреждение — палитра плашки «только чтение» (ReadOnlyBanner).
  warning: {
    container: 'border-amber-300 bg-amber-50',
    title: 'text-amber-900',
    message: 'text-amber-800',
    icon: TriangleAlert,
    iconColor: '#b45309',
  },
  info: {
    container: 'border-border bg-card',
    title: 'text-app',
    message: 'text-muted',
    icon: Info,
    iconColor: '#111827',
  },
  success: {
    container: 'border-ok-fg bg-ok-bg',
    title: 'text-ok-fg',
    message: 'text-ok-fg',
    icon: CircleCheck,
    iconColor: '#166534',
  },
};

/** Одна плашка уведомления: проявляется и чуть съезжает вниз при появлении. */
function NotificationToast({ notification, onDismiss }: { notification: AppNotification; onDismiss: () => void }) {
  /**
   * 0 — только появилось, 1 — на месте.
   *
   * `useState` с ленивым начальным значением, а не `useRef`: объект анимации
   * читается при рендере (в `style`), а правило react-hooks/refs запрещает
   * обращаться к ref во время рендера. Само значение состояния не меняется,
   * поэтому лишних рендеров это не вызывает.
   */
  const [progress] = useState(() => new Animated.Value(0));
  const style = KIND_STYLES[notification.kind];
  const Icon = style.icon;

  useEffect(() => {
    Animated.timing(progress, {
      toValue: 1,
      duration: 180,
      easing: Easing.out(Easing.quad),
      useNativeDriver: Platform.OS !== 'web',
    }).start();
  }, [progress]);

  return (
    <Animated.View
      style={{
        opacity: progress,
        transform: [{ translateY: progress.interpolate({ inputRange: [0, 1], outputRange: [-12, 0] }) }],
      }}
      // Клики снаружи плашки не перехватываем (`box-none`): у обёртки нет
      // заливки, и она не должна «съедать» нажатия мимо тоста.
      pointerEvents="box-none"
    >
      {/* Клик по плашке закрывает её: это быстрее, чем целиться в крестик. */}
      <Pressable
        accessibilityRole="alert"
        accessibilityLabel={notification.title ?? notification.message}
        onPress={onDismiss}
        pointerEvents="auto"
        className={['flex-row items-start gap-2.5 rounded-xl border px-3.5 py-3', style.container].join(' ')}
      >
        <Icon size={18} color={style.iconColor} />

        <View className="flex-1 gap-0.5">
          {notification.title === undefined ? null : (
            <Text className={['text-sm font-semibold', style.title].join(' ')}>{notification.title}</Text>
          )}

          <Text className={['text-xs leading-relaxed', style.message].join(' ')}>{notification.message}</Text>
        </View>

        <Pressable
          accessibilityRole="button"
          accessibilityLabel="Закрыть уведомление"
          onPress={onDismiss}
          hitSlop={8}
          pointerEvents="auto"
          className="-my-1 -mr-1 h-6 w-6 items-center justify-center rounded-lg active:bg-black/5"
        >
          <X size={14} color={style.iconColor} />
        </Pressable>
      </Pressable>
    </Animated.View>
  );
}

/**
 * Хост уведомлений: прозрачная модалка без затемнения (её видимость зависит от
 * длины очереди).
 *
 * Подложка не перехватывает нажатия (`pointerEvents="box-none"`) — пользователь
 * продолжает работать с доской, пока на экране висит сообщение; кликабельны
 * только сами плашки.
 */
export function NotificationHost() {
  const { notifications, dismiss } = useNotifications();
  const { width } = useWindowDimensions();
  const insets = useSafeAreaInsets();
  const compact = width < COMPACT_MAX_WIDTH;
  /** Отступ сверху: статус-бар/челка плюс запас под хедер стека. */
  const top = insets.top + HEADER_RESERVE;

  return (
    <Modal
      visible={notifications.length > 0}
      transparent
      animationType="none"
      statusBarTranslucent
      // Тост должен лежать поверх открытых попапов: на native их модалки идут
      // отдельными окнами, поэтому наш контейнер поднимается на ступень выше,
      // а сами модалки опускают свой (TOAST_MODAL_STYLE).
      style={TOAST_MODAL_STYLE}
    >
      {/*
        `box-none` пропом, а не классом `pointer-events-none`: без него модалка
        на web перекрывает доску целиком и забирает все клики (см. шапку файла).
      */}
      <View pointerEvents="box-none" className="flex-1" style={{ paddingTop: top, paddingHorizontal: SCREEN_MARGIN }}>
        <View pointerEvents="box-none" className={compact ? 'gap-2' : 'items-end gap-2'}>
          {notifications.map((notification) => (
            <View
              key={notification.id}
              pointerEvents="box-none"
              style={compact ? undefined : { width: WIDE_TOAST_WIDTH, maxWidth: '100%' }}
              className={compact ? 'w-full' : undefined}
            >
              <NotificationToast notification={notification} onDismiss={() => dismiss(notification.id)} />
            </View>
          ))}
        </View>
      </View>
    </Modal>
  );
}
