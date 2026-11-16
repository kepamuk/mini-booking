<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Skeleton from 'primevue/skeleton';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import {
  ArrowUpRight,
  ArrowRight,
  CalendarDays,
  Clock3,
  DoorOpen,
  Leaf,
  MapPin,
  Monitor,
  RefreshCw,
  ShieldCheck,
  Users,
  Wifi,
  X,
} from 'lucide-vue-next';
import { ApiError, useBooking, type FieldErrors, type Slot } from './composables/useBooking';

const {
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
} = useBooking();
const toast = useToast();
const selectedDate = ref('');
const bookingOpen = ref(false);
const cancelOpen = ref(false);
const selectedSlot = ref<Slot | null>(null);
const name = ref('');
const email = ref('');
const fieldErrors = ref<FieldErrors>({});
const actionError = ref('');
const conflicted = ref(false);

const today = computed(() =>
  serverNow.value
    ? new Intl.DateTimeFormat('en-CA', {
        timeZone: timezone.value,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
      }).format(serverNow.value)
    : '',
);
watch(dates, (value) => {
  if (!value.includes(selectedDate.value))
    selectedDate.value = value.includes(today.value) ? today.value : (value[0] ?? '');
});
const daySlots = computed(() =>
  slots.value.filter((slot) => slot.starts_at.slice(0, 10) === selectedDate.value),
);
const availableCount = computed(
  () => daySlots.value.filter((slot) => statusOf(slot) === 'available').length,
);
const totalAvailable = computed(
  () => slots.value.filter((slot) => statusOf(slot) === 'available').length,
);
const liveSlot = computed(
  () => slots.value.find((slot) => slot.id === selectedSlot.value?.id) ?? selectedSlot.value,
);
const canBook = computed(
  () => liveSlot.value && statusOf(liveSlot.value) === 'available' && !conflicted.value,
);
const statusLabels = {
  available: 'Свободно',
  booked: 'Забронировано',
  past: 'Время прошло',
};

function dateFormat(date: string, options: Intl.DateTimeFormatOptions) {
  // Noon UTC is used only for calendar labels; slot times use the actual schedule timezone.
  return new Intl.DateTimeFormat('ru-RU', {
    ...options,
    timeZone: 'UTC',
  }).format(new Date(`${date}T12:00:00Z`));
}
function time(value: string) {
  return new Intl.DateTimeFormat('ru-RU', {
    hour: '2-digit',
    minute: '2-digit',
    timeZone: timezone.value,
  }).format(new Date(value));
}
function range(slot: Slot) {
  return `${time(slot.starts_at)} – ${time(slot.ends_at)}`;
}
function freeLabel(count: number) {
  const mod = count % 100;
  return `${count} ${mod >= 11 && mod <= 14 ? 'свободных слотов' : count % 10 === 1 ? 'свободный слот' : count % 10 >= 2 && count % 10 <= 4 ? 'свободных слота' : 'свободных слотов'}`;
}
function dayFreeCount(date: string) {
  return slots.value.filter(
    (slot) => slot.starts_at.slice(0, 10) === date && statusOf(slot) === 'available',
  ).length;
}
function openSlot(slot: Slot) {
  if (pending.value || loading.value) return;
  selectedSlot.value = { ...slot };
  actionError.value = '';
  fieldErrors.value = {};
  conflicted.value = false;
  if (slot.booking_id !== null) cancelOpen.value = true;
  else if (statusOf(slot) === 'available') bookingOpen.value = true;
}
async function submitBooking() {
  if (!selectedSlot.value || pending.value || !canBook.value) return;
  fieldErrors.value = {};
  actionError.value = '';
  try {
    const id = await book(selectedSlot.value, name.value, email.value);
    bookingOpen.value = false;
    name.value = '';
    email.value = '';
    toast.add({
      severity: 'success',
      summary: 'Время для ваших идей забронировано',
      detail: `Бронирование №${id} · ${range(selectedSlot.value)}`,
      life: 6000,
    });
  } catch (error) {
    if (error instanceof ApiError) {
      fieldErrors.value = error.errors;
      conflicted.value = error.status === 409;
      actionError.value = error.status === 422 ? 'Проверьте данные в форме.' : error.message;
    } else actionError.value = 'Не удалось забронировать слот. Повторите попытку.';
  }
}
async function submitCancellation() {
  if (!selectedSlot.value || pending.value) return;
  actionError.value = '';
  try {
    await cancel(selectedSlot.value);
    cancelOpen.value = false;
    toast.add({
      severity: 'success',
      summary: 'Бронирование отменено',
      detail: 'Будущее время снова доступно для встречи.',
      life: 4500,
    });
  } catch (error) {
    actionError.value = (error as Error).message;
  }
}
</script>

<template>
  <Toast position="top-right" />
  <header class="topbar">
    <a href="/" class="brand" aria-label="Mini Booking — главная">
      <span class="brand-mark"><DoorOpen :size="21" :stroke-width="1.8" /></span>
      mini<span class="brand-light">booking</span><span class="brand-dot">.</span>
    </a>
    <nav aria-label="Главная навигация">
      <a href="#schedule" class="nav-link"><CalendarDays :size="16" /> Расписание</a>
    </nav>
    <div class="office-label">
      <span class="online-dot"></span> Пространство для команды
      <span class="office-avatar">М</span>
    </div>
  </header>

  <main class="page">
    <div class="intro">
      <div>
        <p class="eyebrow"><span></span> МЕНЬШЕ ОРГАНИЗАЦИИ. БОЛЬШЕ ИДЕЙ.</p>
        <h1>Хорошим идеям<br />нужно <span>своё место.</span></h1>
        <p class="intro-copy">Соберите команду. Выберите время. Начните что-то большое.</p>
      </div>
      <div class="intro-note">
        <Leaf :size="27" :stroke-width="1.3" />
        <p>Пространство, в котором<br />легко думать вместе.</p>
      </div>
    </div>

    <div class="workspace">
      <aside class="room-sidebar" aria-label="О переговорной">
        <article class="room-card">
          <div class="room-photo">
            <img
              src="/images/meeting-room.jpg"
              alt="Светлая переговорная с большим столом и панорамными окнами"
              width="1400"
              height="933"
            />
            <span class="photo-badge"><span class="online-dot"></span> ВАШЕ МЕСТО ДЛЯ ВСТРЕЧ</span>
            <div class="photo-caption">
              <span>Вдохновение начинается здесь</span><ArrowUpRight :size="23" />
            </div>
          </div>
          <div class="room-details">
            <div class="room-title">
              <div>
                <span class="room-number">ПЕРЕГОВОРНАЯ / 01</span>
                <h2>Место встречи</h2>
              </div>
              <span class="room-icon"><DoorOpen :size="23" :stroke-width="1.4" /></span>
            </div>
            <p class="room-description">
              Для смелых планов, свежих взглядов<br class="desktop-break" />
              и разговоров по существу.
            </p>
            <div class="amenities">
              <span><Users :size="16" /> До 6 человек</span
              ><span><Monitor :size="16" /> Большой экран</span
              ><span><Wifi :size="16" /> Быстрый Wi-Fi</span
              ><span><Leaf :size="16" /> Дневной свет</span>
            </div>
            <div class="hours">
              <Clock3 :size="17" /><span>Каждый день</span><strong>09:00 — 18:00</strong>
            </div>
          </div>
        </article>
        <div class="help-card">
          <span class="help-icon"><ShieldCheck :size="21" :stroke-width="1.5" /></span>
          <div>
            <h3>Планы могут меняться</h3>
            <p>Отмените бронь в любой момент —<br />и освободите место для других.</p>
          </div>
        </div>
        <p class="demo-note">
          Демо без авторизации: отмена доступна любому посетителю. Имя и email остаются приватными.
        </p>
      </aside>

      <section id="schedule" class="schedule" aria-labelledby="schedule-title" :aria-busy="loading">
        <div class="schedule-heading">
          <div>
            <p class="section-kicker">ВАША СЛЕДУЮЩАЯ ВСТРЕЧА</p>
            <h2 id="schedule-title">Выберите время<span class="small-dot">.</span></h2>
            <p class="section-description">Один слот — один час продуктивной работы.</p>
          </div>
          <Button
            class="refresh-button"
            severity="secondary"
            variant="text"
            rounded
            :disabled="loading || pending"
            aria-label="Обновить расписание"
            title="Обновить расписание"
            @click="load"
            ><RefreshCw :size="18" :class="{ spinning: loading }"
          /></Button>
        </div>

        <Message v-if="loadError" severity="error" :closable="false" class="load-error"
          ><div class="error-content">
            <span>{{ loadError }}</span><Button
              label="Повторить"
              size="small"
              severity="danger"
              variant="outlined"
              :disabled="loading || pending"
              @click="load"
            /></div
        ></Message>

        <template v-if="loading && !slots.length">
          <div class="date-strip skeleton-dates">
            <Skeleton v-for="i in 7" :key="i" height="98px" />
          </div>
          <div class="slot-grid">
            <Skeleton v-for="i in 9" :key="i" height="127px" border-radius="14px" />
          </div>
        </template>
        <template v-else-if="slots.length">
          <div class="date-strip" role="group" aria-label="Фильтр по дате">
            <button
              v-for="date in dates"
              :key="date"
              class="date-button"
              :class="{
                selected: selectedDate === date,
                'is-today': date === today,
              }"
              :aria-pressed="selectedDate === date"
              :aria-label="
                dateFormat(date, {
                  day: 'numeric',
                  month: 'long',
                  weekday: 'long',
                })
              "
              @click="selectedDate = date"
            >
              <span class="day-week">{{
                date === today ? 'СЕГОДНЯ' : dateFormat(date, { weekday: 'short' })
              }}</span><span class="day-number">{{ dateFormat(date, { day: '2-digit' }) }}</span
              ><span class="day-indicator" :class="{ empty: !dayFreeCount(date) }"></span>
            </button>
          </div>
          <div class="day-heading">
            <h3>
              {{
                dateFormat(selectedDate, {
                  day: 'numeric',
                  month: 'long',
                  weekday: 'long',
                })
              }}
            </h3>
            <span class="timezone"><MapPin :size="13" /> {{ timezone }}</span>
          </div>
          <div class="slot-grid">
            <Button
              v-for="slot in daySlots"
              :key="slot.id"
              class="slot-card"
              :class="`slot-${statusOf(slot)}`"
              :disabled="
                pending || loading || (statusOf(slot) === 'past' && slot.booking_id === null)
              "
              :aria-label="`${range(slot)}, ${statusLabels[statusOf(slot)]}${slot.booking_id !== null ? ', отменить бронирование' : ''}`"
              @click="openSlot(slot)"
            >
              <div class="slot-top">
                <Clock3 :size="16" :stroke-width="1.5" /><span
                  v-if="slot.booking_id !== null"
                  class="booking-number"
                  >№{{ slot.booking_id }}</span
                ><ArrowUpRight
                  v-else-if="statusOf(slot) === 'available'"
                  class="slot-arrow"
                  :size="17"
                />
              </div>
              <strong class="slot-time">{{ range(slot) }}</strong>
              <span class="slot-status"
                ><span class="status-dot"></span>{{ statusLabels[statusOf(slot)]
                }}<span v-if="slot.booking_id !== null" class="cancel-hint"
                  >Отменить <X :size="11" /></span
              ></span>
            </Button>
          </div>
          <div class="schedule-bottom">
            <span class="availability"
              ><span class="online-dot"></span>{{ freeLabel(availableCount) }} на этот день</span
            ><span class="legend-past"><span class="status-dot"></span> Прошедшее время</span>
          </div>
          <div v-if="!availableCount" class="no-availability">
            <CalendarDays :size="20" />
            <p>
              На этот день свободного времени нет.<br /><strong
                >Посмотрите другие даты — место для встречи найдётся.</strong
              >
            </p>
          </div>
          <div class="booking-tip">
            <span class="tip-step">01 — 02</span>
            <p>
              Выберите свободный слот и оставьте имя и email.<br class="desktop-break" />
              Ваша встреча появится в расписании сразу.
            </p>
            <ArrowRight :size="23" :stroke-width="1.3" />
          </div>
        </template>
        <div v-else-if="!loadError" class="empty-state">
          <CalendarDays :size="35" :stroke-width="1.2" />
          <h3>Расписание ещё не добавлено</h3>
          <p>Слоты появятся, когда расписание будет готово.</p>
          <Button label="Обновить" severity="secondary" variant="outlined" @click="load" />
        </div>
      </section>
    </div>
    <footer class="footer">
      <span
        >mini booking<span class="brand-dot">.</span> <span class="footer-divider">/</span> Вместе
        получается больше.</span
      ><span><span class="online-dot"></span>{{ totalAvailable }} слотов для новых идей</span>
    </footer>
  </main>

  <Dialog
    v-model:visible="bookingOpen"
    modal
    class="booking-dialog"
    :style="{ width: '460px' }"
    :breakpoints="{ '560px': 'calc(100vw - 32px)' }"
    :closable="!pending"
    :close-on-escape="!pending"
    :draggable="false"
    header="Время для хороших идей"
  >
    <div v-if="selectedSlot" class="dialog-slot">
      <span class="dialog-icon"><CalendarDays :size="21" /></span>
      <div>
        <strong
          >{{
            dateFormat(selectedSlot.starts_at.slice(0, 10), {
              day: 'numeric',
              month: 'long',
            })
          }}
          · {{ range(selectedSlot) }}</strong
        >
        <p>Место встречи · 1 час · {{ timezone }}</p>
      </div>
    </div>
    <form class="booking-form" @submit.prevent="submitBooking">
      <p class="form-intro">Представьтесь, и переговорная ваша.</p>
      <div class="field">
        <label for="customer-name">Ваше имя <span>*</span></label
        ><InputText
          id="customer-name"
          v-model="name"
          autofocus
          name="customer_name"
          autocomplete="name"
          placeholder="Например, Александр"
          required
          maxlength="100"
          :disabled="pending"
          :invalid="!!fieldErrors.customer_name"
          :aria-invalid="!!fieldErrors.customer_name"
          :aria-describedby="fieldErrors.customer_name ? 'name-error' : undefined"
          @update:model-value="delete fieldErrors.customer_name"
        /><small v-if="fieldErrors.customer_name" id="name-error" class="field-error">{{
          fieldErrors.customer_name[0]
        }}</small>
      </div>
      <div class="field">
        <label for="customer-email">Email <span>*</span></label
        ><InputText
          id="customer-email"
          v-model="email"
          name="customer_email"
          type="email"
          autocomplete="email"
          placeholder="you@company.ru"
          required
          maxlength="254"
          :disabled="pending"
          :invalid="!!fieldErrors.customer_email"
          :aria-invalid="!!fieldErrors.customer_email"
          :aria-describedby="fieldErrors.customer_email ? 'email-error' : undefined"
          @update:model-value="delete fieldErrors.customer_email"
        /><small v-if="fieldErrors.customer_email" id="email-error" class="field-error">{{
          fieldErrors.customer_email[0]
        }}</small>
      </div>
      <Message v-if="actionError" severity="error" :closable="false">{{ actionError }}</Message>
      <Message v-else-if="!canBook && !pending" severity="warn" :closable="false"
        >Этот слот уже недоступен. Выберите другое время.</Message
      >
      <Message v-if="fieldErrors.slot_id" severity="error" :closable="false">{{
        fieldErrors.slot_id[0]
      }}</Message>
      <Button
        v-if="canBook || pending"
        type="submit"
        :loading="pending"
        :disabled="pending"
        class="submit-button"
        ><span>{{ pending ? 'Бронируем…' : 'Забронировать переговорную' }}</span
        ><ArrowRight v-if="!pending" :size="17"
      /></Button>
      <Button
        v-else
        type="button"
        label="Выбрать другое время"
        class="submit-button"
        :disabled="pending"
        @click="bookingOpen = false"
      />
      <p class="form-privacy"><ShieldCheck :size="14" /> Имя и email не видны в расписании</p>
    </form>
  </Dialog>

  <Dialog
    v-model:visible="cancelOpen"
    modal
    class="booking-dialog"
    :style="{ width: '440px' }"
    :breakpoints="{ '560px': 'calc(100vw - 32px)' }"
    :closable="!pending"
    :close-on-escape="!pending"
    :draggable="false"
    header="Освободить время?"
  >
    <div v-if="selectedSlot" class="dialog-slot">
      <span class="dialog-icon"><CalendarDays :size="21" /></span>
      <div>
        <strong
          >{{
            dateFormat(selectedSlot.starts_at.slice(0, 10), {
              day: 'numeric',
              month: 'long',
            })
          }}
          · {{ range(selectedSlot) }}</strong
        >
        <p>Бронирование №{{ selectedSlot.booking_id }} · {{ timezone }}</p>
      </div>
    </div>
    <p class="cancel-copy">После отмены будущий слот снова станет доступен для команды.</p>
    <Message v-if="actionError" severity="error" :closable="false" class="cancel-error">{{
      actionError
    }}</Message>
    <div class="cancel-actions">
      <Button
        label="Оставить бронь"
        severity="secondary"
        variant="outlined"
        :disabled="pending"
        @click="cancelOpen = false"
      /><Button
        :label="pending ? 'Отменяем…' : 'Отменить бронь'"
        severity="danger"
        :loading="pending"
        :disabled="pending"
        @click="submitCancellation"
      />
    </div>
  </Dialog>
</template>
