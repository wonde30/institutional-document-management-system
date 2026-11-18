#!/usr/bin/env node
import { readFileSync, openSync, writeSync, closeSync } from 'fs'

const file = 'client/src/layouts/AppLayout.vue'
let content = readFileSync(file, 'utf8')

// Add uppercase to the avatar span
content = content.replace(
  /class="w-7 h-7 rounded-full bg-primary-subtle text-primary font-bold text-xs flex items-center justify-center border border-primary\/20"/,
  'class="w-7 h-7 rounded-full bg-primary-subtle text-primary font-bold text-xs flex items-center justify-center border border-primary/20 uppercase"'
)

const buffer = Buffer.from(content, 'utf8')
const fd = openSync(file, 'w')
writeSync(fd, buffer)
closeSync(fd)

console.log('✓ Added uppercase class to user initials avatar')
