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
 * кликам она не мешает: подложка прозрачна для нажатий (`pointer-events-none`),
 * а плашка включает их обратно (`pointer-events-auto`).
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
      className="pointer-events-auto"
    >
      {/* Клик по плашке закрывает её: это быстрее, чем целиться в крестик. */}
      <Pressable
        accessibilityRole="alert"
        accessibilityLabel={notification.title ?? notification.message}
        onPress={onDismiss}
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
 * Подложка не перехватывает нажатия (`pointer-events-none`) — пользователь
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
    <Modal visible={notifications.length > 0} transparent animationType="none" statusBarTranslucent>
      <View className="pointer-events-none flex-1" style={{ paddingTop: top, paddingHorizontal: SCREEN_MARGIN }}>
        <View className={compact ? 'gap-2' : 'items-end gap-2'}>
          {notifications.map((notification) => (
            <View
              key={notification.id}
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
