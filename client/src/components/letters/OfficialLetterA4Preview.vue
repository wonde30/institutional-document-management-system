<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    refNumber?: string
    ethiopianDate?: string
    recipientLead?: string
    recipientSub?: string
    recipientLocation?: string
    subject?: string
    body?: string
    ccList?: string[]
    ccLocation?: string
    signatoryName?: string
    signatoryTitleAm?: string
    signatoryTitleEn?: string
    uniAm?: string
    facultyAm?: string
    deptAm?: string
    locationAm?: string
    uniEn?: string
    facultyEn?: string
    deptEn?: string
    locationEn?: string
    emblemUrl?: string | null
    stampUrl?: string | null
    isDigitallySigned?: boolean
    digitalSignature?: {
      signer_name?: string
      signed_at?: string
      provider_transaction_reference?: string
      verification_status?: string
    } | null
    phone?: string
    fax?: string
    pobox?: string
    noticeAm?: string
    noticeEn?: string
  }>(),
  {
    refNumber: 'WU/DOC/DRAFT',
    ethiopianDate: '',
    recipientLead: '',
    recipientSub: '',
    recipientLocation: 'ኮምቦልቻ፤',
    subject: '',
    body: '',
    ccList: () => [],
    ccLocation: 'ኮምቦልቻ፤',
    signatoryName: '',
    signatoryTitleAm: '',
    signatoryTitleEn: '',
    uniAm: 'ወሎ ዩኒቨርሲቲ',
    facultyAm: 'ኮምቦልቻ ቴክኖሎጂ ኢንስቲትዩት',
    deptAm: 'ኢንፎርሜሽን ቴክኖሎጂ ትምህርት ክፍል',
    locationAm: 'ኮምቦልቻ',
    uniEn: 'WOLLO UNIVERSITY',
    facultyEn: 'KOMBOLCHA INSTITUTE OF TECHNOLOGY',
    deptEn: 'Information Technology Department',
    locationEn: 'Kombolcha',
    emblemUrl: null,
    stampUrl: null,
    isDigitallySigned: false,
    digitalSignature: null,
    phone: undefined,
    fax: undefined,
    pobox: undefined,
    noticeAm: 'መልስ ሲፅፉ የደብዳቤያችንን ቁጥርና ቀን ይጠቅሱ',
    noticeEn: 'please quote our ref. number when replying',
  }
)

const paragraphs = computed(() => {
  if (!props.body) return []
  return props.body
    .split('\n')
    .map((p) => p.trim())
    .filter((p) => p.length > 0)
})
</script>

<template>
  <div class="a4-document-container bg-white text-slate-900 shadow-lg border border-slate-200 rounded-sm p-6 sm:p-10 max-w-[780px] mx-auto select-text font-serif leading-relaxed text-[13px] relative overflow-hidden">
    <!-- Institutional Asset Warning in Draft Preview -->
    <div v-if="!emblemUrl" class="mb-3 px-3 py-1.5 rounded bg-amber-50 border border-amber-200 text-amber-900 text-xs flex items-center justify-between font-sans">
      <div class="flex items-center gap-1.5">
        <span class="font-bold">&#9888; Institutional Asset Notice:</span>
        <span>Approved Wollo University emblem is pending installation. Draft preview only &mdash; final issuance requires approved emblem.</span>
      </div>
      <span class="text-[9.5px] px-2 py-0.5 rounded bg-amber-100 text-amber-800 font-semibold">Draft preview</span>
    </div>

    <!-- 3-Column Institutional Header -->
    <div class="grid grid-cols-12 items-center gap-2 pb-2">
      <!-- Left: Amharic Unit Hierarchy -->
      <div class="col-span-5 text-left leading-tight text-slate-800">
        <div class="font-bold text-[14px] text-[#002855]">{{ uniAm }}</div>
        <div class="font-bold text-[12px] text-slate-800 mt-0.5">{{ facultyAm }}</div>
        <div class="text-xs text-slate-700 mt-0.5">{{ deptAm }}</div>
        <div class="text-xs text-slate-700">{{ locationAm }}</div>
      </div>

      <!-- Center: Approved Wollo University Emblem or Reserved Institutional Frame -->
      <div class="col-span-2 flex justify-center items-center">
        <img
          v-if="emblemUrl"
          :src="emblemUrl"
          alt="Wollo University Approved Emblem"
          class="w-16 h-16 object-contain"
        />
        <div
          v-else
          class="w-16 h-16 rounded-full border border-dashed border-amber-300 bg-amber-50/50 flex flex-col justify-center items-center text-center p-1"
          title="Official emblem pending institutional asset installation"
        >
          <span class="text-[6.5px] font-bold text-amber-700 leading-tight uppercase font-sans">
            Emblem<br>Pending
          </span>
        </div>
      </div>

      <!-- Right: English Unit Hierarchy -->
      <div class="col-span-5 text-right leading-tight text-slate-800">
        <div class="font-bold text-[13px] text-[#002855] uppercase">{{ uniEn }}</div>
        <div class="font-bold text-xs text-slate-800 uppercase mt-0.5">{{ facultyEn }}</div>
        <div class="text-xs text-slate-700 mt-0.5">{{ deptEn }}</div>
        <div class="text-xs text-slate-700">{{ locationEn }}</div>
      </div>
    </div>

    <!-- Strong Horizontal Divider Line -->
    <div class="border-b-[2.5px] border-[#002855] my-2"></div>

    <!-- Metadata Block (Ref No & Date) -->
    <div class="flex justify-end text-right my-3 text-[12px] leading-snug">
      <div>
        <div>
          <span>ቁጥር/Ref.No:- </span>
          <span class="font-bold underline decoration-slate-400">{{ refNumber || 'WU/DOC/DRAFT' }}</span>
        </div>
        <div class="mt-1">
          <span>ቀን/Date:- </span>
          <span class="font-bold underline decoration-slate-400">{{ ethiopianDate || '---' }}</span>
        </div>
      </div>
    </div>

    <!-- Recipient Block -->
    <div class="my-4 text-[13px] leading-snug text-slate-900">
      <div class="flex items-start gap-1 font-bold">
        <span class="text-slate-800 font-bold">&#10148;</span>
        <span>{{ recipientLead || 'ለሚመለከተው አካል' }}</span>
      </div>
      <div v-if="recipientSub" class="pl-4 text-slate-800">
        ({{ recipientSub }})
      </div>
      <div v-if="recipientLocation" class="pl-4 text-slate-800">
        {{ recipientLocation }}
      </div>
    </div>

    <!-- Centered Bold Underlined Subject Line -->
    <div class="text-center font-bold text-[13.5px] my-5">
      <span class="underline decoration-slate-800 decoration-1 underline-offset-4">
        ጉዳዩ፡- {{ subject || 'የደብዳቤ ጉዳይ' }}፤
      </span>
    </div>

    <!-- Main Letter Content -->
    <div class="letter-body-content text-justify text-[12.5px] leading-relaxed space-y-3 mb-6 text-slate-900">
      <template v-if="paragraphs.length > 0">
        <p v-for="(p, idx) in paragraphs" :key="idx" class="indent-7">
          {{ p }}
        </p>
      </template>
      <template v-else>
        <p class="indent-7 text-slate-400 italic">
          [ደብዳቤው ይዘት እዚህ ይጻፋል... The main letter body text will appear formatted here with authentic institutional typography.]
        </p>
      </template>
    </div>

    <!-- Lower Section: CC Distribution (Left) & Signatory + Stamp (Right) -->
    <div class="grid grid-cols-12 gap-4 items-start pt-3 border-t border-slate-100 my-4">
      <!-- CC Distribution List (Lower Left) -->
      <div class="col-span-7 text-xs leading-normal text-slate-800">
        <div v-if="ccList && ccList.length > 0">
          <div class="font-bold underline mb-1 text-[11.5px]">ግልባጭ፤</div>
          <ul class="space-y-0.5">
            <li v-for="(cc, cidx) in ccList" :key="cidx" class="flex items-start gap-1">
              <span class="text-[9px] mt-0.5">&#10146;</span>
              <span>{{ cc }}</span>
            </li>
          </ul>
          <div v-if="ccLocation" class="pl-3 mt-1 text-slate-700">
            {{ ccLocation }}
          </div>
        </div>
      </div>

      <!-- Signatory & Unit Stamp (Lower Right) -->
      <div class="col-span-5 text-right text-[12px] leading-tight relative">
        <div class="font-bold text-[12.5px] mb-2 text-slate-900">
          &laquo; ከሰላምታ ጋር &raquo;
        </div>

        <!-- Authentic Signature Block -->
        <!-- When digitally signed with cryptographic proof -->
        <div v-if="isDigitallySigned" class="my-2 p-2 rounded border border-sky-300 bg-sky-50 text-left text-xs text-sky-900 font-sans leading-tight">
          <div class="font-bold text-sky-800 flex items-center gap-1">
            <span>&#128274; DIGITALLY SIGNED &amp; VERIFIED</span>
          </div>
          <div class="mt-0.5">Signer: {{ digitalSignature?.signer_name || signatoryName }}</div>
          <div v-if="digitalSignature?.signed_at" class="text-sky-700">Time: {{ digitalSignature.signed_at }}</div>
        </div>
        <!-- Standard blank signature line reserving physical signature execution -->
        <div v-else class="h-10 flex justify-end items-end my-1">
          <div class="w-36 border-b border-dotted border-slate-400"></div>
        </div>

        <div class="font-bold text-[12.5px] text-slate-900">{{ signatoryName || 'Authorized Signatory' }}</div>
        <div v-if="signatoryTitleAm" class="font-bold text-xs text-slate-800 mt-0.5">{{ signatoryTitleAm }}</div>
        <div v-if="signatoryTitleEn" class="text-[10.5px] text-slate-600">{{ signatoryTitleEn }}</div>

        <!-- Official Circular Unit Stamp or Reserved Boundary -->
        <div class="flex justify-end mt-2">
          <!-- When approved stamp file is available -->
          <img
            v-if="stampUrl"
            :src="stampUrl"
            alt="Official Approved Unit Stamp"
            class="w-24 h-24 object-contain"
          />
          <!-- Reserved physical ink seal boundary -->
          <div
            v-else
            class="w-20 h-20 rounded-full border border-dashed border-slate-300 bg-slate-50/50 flex flex-col justify-center items-center text-center p-1 select-none"
            title="Designated blank circular boundary for physical ink stamping"
          >
            <span class="text-[6.5px] font-bold text-slate-400 font-sans tracking-tight uppercase leading-tight">
              Physical Stamp<br>Area
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Institutional Footer with Divider -->
    <div class="border-t-[1.5px] border-[#002855] pt-2 mt-6 grid grid-cols-2 text-[9px] text-slate-600 leading-tight">
      <div class="text-left space-y-0.5">
        <div v-if="phone">&#9742; {{ phone }}</div>
        <div v-if="fax">Fax: {{ fax }}</div>
        <div v-if="pobox">&#9993; {{ pobox }}</div>
      </div>
      <div class="text-right">
        <div v-if="noticeAm" class="font-medium text-slate-800">{{ noticeAm }}</div>
        <div v-if="noticeEn" class="italic text-slate-500 text-[8px]">{{ noticeEn }}</div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.a4-document-container {
  font-family: 'Nyala', 'DejaVu Sans', serif, sans-serif;
}
</style>
