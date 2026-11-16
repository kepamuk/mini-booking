import { computed, onMounted, onUnmounted, ref } from 'vue';

export type SlotStatus = 'available' | 'booked' | 'past';
export interface Slot {
  id: number;
  starts_at: string;
  ends_at: string;
  status: SlotStatus;
  booking_id: number | null;
}
interface Schedule {
  data: Slot[];
  meta: { timezone: string; server_time: string };
}
export type FieldErrors = Record<string, string[]>;

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public errors: FieldErrors = {},
  ) {
    super(message);
  }
}

async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  const controller = new AbortController();
  const timeout = window.setTimeout(() => controller.abort(), 15_000);
  try {
    const response = await fetch(`/api${path}`, {
      ...options,
      signal: controller.signal,
      headers: {
        Accept: 'application/json',
        ...(options.body ? { 'Content-Type': 'application/json' } : {}),
      },
    });
    if (response.status === 204) return undefined as T;
    const body = await response.json().catch(() => null);
    if (!response.ok) {
      const message =
        response.status >= 500
          ? 'Сервер временно недоступен. Попробуйте ещё раз.'
          : (body?.message ?? 'Не удалось выполнить запрос. Попробуйте ещё раз.');
      throw new ApiError(response.status, message, body?.errors);
    }
    if (!body) throw new ApiError(0, 'Сервер вернул некорректный ответ. Попробуйте ещё раз.');
    return body as T;
  } catch (error) {
    if (error instanceof ApiError) throw error;
    throw new ApiError(
      0,
      'Не удалось связаться с сервером. Проверьте подключение и повторите действие.',
    );
  } finally {
    window.clearTimeout(timeout);
  }
}

export function useBooking() {
  const slots = ref<Slot[]>([]);
  const timezone = ref('Europe/Moscow');
  const loading = ref(true);
  const pending = ref(false);
  const loadError = ref('');
  const serverNow = ref(0);
  let syncedAt = 0;
  let syncedTime = 0;
  let loadVersion = 0;
  let timer: number | undefined;

  function statusOf(slot: Slot): SlotStatus {
    if (new Date(slot.starts_at).getTime() <= serverNow.value) return 'past';
    return slot.booking_id !== null ? 'booked' : 'available';
  }

  async function load() {
    const version = ++loadVersion;
    loading.value = true;
    loadError.value = '';
    try {
      const schedule = await request<Schedule>('/slots');
      if (version !== loadVersion) return;
      slots.value = schedule.data;
      timezone.value = schedule.meta.timezone;
      syncedTime = Date.parse(schedule.meta.server_time);
      syncedAt = performance.now();
      serverNow.value = syncedTime;
    } catch (error) {
      if (version === loadVersion) loadError.value = (error as Error).message;
    } finally {
      if (version === loadVersion) loading.value = false;
    }
  }

  async function book(slot: Slot, name: string, email: string) {
    pending.value = true;
    try {
      const result = await request<{ id: number }>('/bookings', {
        method: 'POST',
        body: JSON.stringify({
          slot_id: slot.id,
          customer_name: name.trim(),
          customer_email: email.trim(),
        }),
      });
      const current = slots.value.find((item) => item.id === slot.id);
      if (current) {
        current.booking_id = result.id;
        current.status = 'booked';
      }
      await load();
      return result.id;
    } catch (error) {
      if (error instanceof ApiError && error.status === 409) await load();
      throw error;
    } finally {
      pending.value = false;
    }
  }

  async function cancel(slot: Slot) {
    if (slot.booking_id === null) return;
    pending.value = true;
    try {
      await request<void>(`/bookings/${slot.booking_id}`, {
        method: 'DELETE',
      });
      const current = slots.value.find((item) => item.id === slot.id);
      if (current) {
        current.booking_id = null;
        current.status = statusOf(current);
      }
      await load();
    } finally {
      pending.value = false;
    }
  }

  const dates = computed(() => [
    ...new Set(slots.value.map((slot) => slot.starts_at.slice(0, 10))),
  ]);
  function refreshOnFocus() {
    if (!pending.value && document.visibilityState === 'visible') void load();
  }

  onMounted(() => {
    void load();
    timer = window.setInterval(() => {
      if (syncedTime) serverNow.value = syncedTime + (performance.now() - syncedAt);
    }, 1000);
    window.addEventListener('focus', refreshOnFocus);
  });
  onUnmounted(() => {
    window.clearInterval(timer);
    window.removeEventListener('focus', refreshOnFocus);
    ++loadVersion;
  });

  return {
    slots,
    dates,
    timezone,
    loading,
    pending,
    loadError,
    serverNow,
    statusOf,
    load,
    book,
    cancel,
  };
}
