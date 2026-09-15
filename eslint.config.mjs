import globals from 'globals';

export default [
  { ignores: ['**/*.min.js', 'node_modules/**'] },
  {
    files: ['js/*.js'],
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'script',
      globals: {
        ...globals.browser,
        ...globals.jquery,
        AmCharts: 'readonly'
      }
    },
    // Correctness checks only: legacy formatting and public functions are valid.
    rules: {
      'constructor-super': 'error',
      'no-class-assign': 'error',
      'no-const-assign': 'error',
      'no-dupe-args': 'error',
      'no-dupe-class-members': 'error',
      'no-duplicate-case': 'error',
      'no-func-assign': 'error',
      'no-setter-return': 'error',
      'no-this-before-super': 'error',
      'no-undef': 'error',
      'no-unexpected-multiline': 'error',
      'no-unreachable': 'error',
      'valid-typeof': 'error'
    }
  }
];
