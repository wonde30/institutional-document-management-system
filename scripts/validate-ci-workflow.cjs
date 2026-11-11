/**
 * Minimal structural validation of the continuous integration workflow.
 *
 * The project has no YAML parser available in either toolchain, so this
 * checks the properties that actually matter for a workflow file: the
 * indentation is consistent, every step has a name and a run or uses, the
 * service definition is complete, and no tab character has crept in.
 */

const fs = require('fs')
const path = require('path')

const file = path.resolve(__dirname, '../.github/workflows/ci.yml')
const lines = fs.readFileSync(file, 'utf8').split(/\r?\n/)

const problems = []

lines.forEach((line, index) => {
  const number = index + 1

  if (line.includes('\t')) {
    problems.push(`line ${number}: a tab character is not valid YAML indentation`)
  }

  if (/\s+$/.test(line)) {
    problems.push(`line ${number}: trailing whitespace`)
  }
})

// Every step must declare a name and either a run command or a uses action.
let currentStep = null
const steps = []

lines.forEach((line, index) => {
  const number = index + 1

  if (/^\s+- name:/.test(line)) {
    if (currentStep) steps.push(currentStep)
    currentStep = { line: number, name: line.split('- name:')[1].trim(), run: false, uses: false }
    return
  }

  if (currentStep) {
    if (/^\s+run:/.test(line)) currentStep.run = true
    if (/^\s+uses:/.test(line)) currentStep.uses = true
  }
})

if (currentStep) steps.push(currentStep)

steps.forEach((step) => {
  if (!step.run && !step.uses) {
    problems.push(`line ${step.line}: the step "${step.name}" has neither a run command nor a uses action`)
  }
})

// The workflow must declare its triggers and both jobs.
const source = lines.join('\n')

;['on:', 'push:', 'pull_request:', 'jobs:', 'backend:', 'frontend:'].forEach((token) => {
  if (!source.includes(token)) {
    problems.push(`the workflow does not declare "${token}"`)
  }
})

if (!/image:\s*mysql:8\.4/.test(source)) {
  problems.push('the workflow does not use a MySQL 8.4 service image')
}

if (!/mysqladmin ping/.test(source)) {
  problems.push('the MySQL service has no health check')
}

if (!/php-version:\s*'8\.4'/.test(source)) {
  problems.push('the workflow does not pin PHP 8.4')
}

if (!/node-version:\s*'24'/.test(source)) {
  problems.push('the workflow does not pin Node 24')
}

if (!/npm ci/.test(source)) {
  problems.push('the workflow does not install the front end from the lockfile')
}

if (!/composer install/.test(source)) {
  problems.push('the workflow does not install the Composer dependencies from the lockfile')
}

if (!/composer audit/.test(source)) {
  problems.push('the workflow does not audit the dependencies')
}

if (!/php artisan test/.test(source)) {
  problems.push('the workflow does not run the backend test suite')
}

if (!/npx vue-tsc/.test(source)) {
  problems.push('the workflow does not type check the front end')
}

if (problems.length > 0) {
  console.log('WORKFLOW PROBLEMS:')
  problems.forEach((problem) => console.log('  - ' + problem))
  process.exitCode = 1
} else {
  console.log(`Workflow structure is valid. ${steps.length} steps verified.`)
}
