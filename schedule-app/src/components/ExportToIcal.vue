<script setup lang="ts">
import { ref, type Ref } from "vue";
import type { CalendarEvent } from "@schedule-x/calendar";
import { type DateValue, DateFormatter, getLocalTimeZone, today } from "@internationalized/date";
import { CalendarIcon } from "lucide-vue-next";
import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { Badge } from "@/components/ui/badge";
import type { ScheduleFilter } from "@/lib/types";
import { dayjs, type Dayjs } from "@/lib/dayjs";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";

const props = defineProps<{
  group: string;
  filters: ScheduleFilter;
  events: CalendarEvent[];
}>();

const value = ref(today(getLocalTimeZone())) as Ref<DateValue>;

const open = ref<boolean>(false);

const df = new DateFormatter("en-US", {
  dateStyle: "long",
});

const escapeText = (text: string) => {
  return text
    .replace(/\\/g, "\\\\")
    .replace(/;/g, "\\;")
    .replace(/,/g, "\\,")
    .replace(/\n/g, "\\n");
};

const parseDate = (dateString: string) => {
  const [datePart, timePart] = dateString.split(" ");
  const [year, month, day] = datePart.split("-").map(Number);
  const [hour, minute] = timePart.split(":").map(Number);
  return new Date(year, month - 1, day, hour, minute);
};

const formatDate = (date: Dayjs): string => {
  return date.format("YYYYMMDD[T]HHmmss").replace(/[-:]/g, "");
};

const getIcsString = () => {
  const icalContent = ["BEGIN:VCALENDAR", "VERSION:2.0", "PRODID:-//ReMoodle//Calendar Export//EN"];

  const end = dayjs(value.value.toDate(getLocalTimeZone()));

  if (!end) {
    return "";
  }

  props.events.forEach((event) => {
    if (event.title && event.description && event.start) {
      const startDate = parseDate(event.start);
      const endDate = end;

      for (let current = dayjs(startDate); current <= endDate; current = current.add(7, "day")) {
        icalContent.push(
          `BEGIN:VEVENT`,
          `UID:${event.id}-${current.toISOString()}`,
          `SUMMARY:${escapeText(event.title)}`,
          `DTSTART:${formatDate(current)}`,
          `DTEND:${formatDate(current.add(50, "minute"))}`,
          `DESCRIPTION:${escapeText(event.description)}`,
          `LOCATION:Astana IT University`,
          `END:VEVENT`,
        );
      }
    }
  });

  icalContent.push("END:VCALENDAR");

  return icalContent.join("\n");
};

const getICalFile = (): void => {
  const blob = new Blob([getIcsString()], {
    type: "text/calendar;charset=utf-8",
  });

  const link = document.createElement("a");
  link.href = URL.createObjectURL(blob);
  link.download = "myCalendar.ics";
  link.click();
  URL.revokeObjectURL(link.href);
};
</script>

<template>
  <Dialog v-model:open="open" @update:open="open = $event">
    <DialogTrigger as-child>
      <Button>Export to .ical file</Button>
    </DialogTrigger>
    <DialogContent class="sm:max-w-[425px]">
      <DialogHeader>
        <DialogTitle>Create iCalendar file</DialogTitle>
        <DialogDescription>
          Make changes to your calendar using filter and choose time frame of events.
        </DialogDescription>
      </DialogHeader>

      <div class="mt-2">
        <h1 class="">
          Selected group
          <Badge variant="outline">{{ group }}</Badge>
        </h1>
      </div>

      <div class="rounded-xl border p-4">
        <div class="">
          <div class="">Event types:</div>
          <div class="my-2 flex select-none gap-1">
            <span v-for="(enabled, type) in filters.eventTypes" :key="type">
              <Badge :variant="enabled ? 'default' : 'destructive'">
                {{ type }}
              </Badge>
            </span>
          </div>
        </div>

        <hr class="my-2" />

        <div class="">
          <div class="">Event formats:</div>
          <div class="my-2 flex select-none gap-1">
            <span v-for="(enabled, format) in filters.eventFormats" :key="format">
              <Badge :variant="enabled ? 'default' : 'destructive'">
                {{ format }}
              </Badge>
            </span>
          </div>
        </div>

        <div v-if="filters.excludedCourses.length > 0">
          <hr class="my-2" />

          <div class="">
            <div class="">Excluded courses:</div>
            <div class="my-2 flex select-none flex-wrap gap-1">
              <span v-for="course in filters.excludedCourses" :key="course">
                <Badge variant="destructive">
                  {{ course }}
                </Badge>
              </span>
            </div>
          </div>
        </div>
      </div>

      <div class="">
        <div class="mb-2">Select end date</div>
        <div class="flex gap-2">
          <Popover>
            <PopoverTrigger as-child>
              <Button variant="outline" class="w-[280px] justify-start text-left font-normal">
                <CalendarIcon class="mr-2 h-4 w-4" />
                {{ value ? df.format(value.toDate(getLocalTimeZone())) : "Pick an end date" }}
              </Button>
            </PopoverTrigger>
            <PopoverContent class="w-auto p-0">
              <Calendar v-model="value" initial-focus />
            </PopoverContent>
          </Popover>
        </div>
      </div>

      <DialogFooter>
        <Button @click="getICalFile" type="submit"> Get file </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
