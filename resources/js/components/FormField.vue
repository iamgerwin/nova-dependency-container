<template>
  <div v-show="isVisible" :class="containerClasses">
    <component
      v-for="(field, index) in field.fields"
      :key="index"
      :is="`form-${field.component}`"
      :resource-name="resourceName"
      :resource-id="resourceId"
      :field="field"
      :errors="errors"
      :show-help-text="showHelpText"
      :mode="mode"
      :via-resource="viaResource"
      :via-resource-id="viaResourceId"
      :via-relationship="viaRelationship"
      @field-changed="handleFieldChanged"
      @file-deleted="$emit('file-deleted')"
      @file-upload-started="$emit('file-upload-started')"
      @file-upload-finished="$emit('file-upload-finished')"
    />
  </div>
</template>

<script>
import { DependentFormField, HandlesValidationErrors } from 'laravel-nova';

export default {
  mixins: [DependentFormField, HandlesValidationErrors],

  props: [
    'resourceName',
    'resourceId',
    'field',
    'errors',
    'showHelpText',
    'mode',
    'viaResource',
    'viaResourceId',
    'viaRelationship',
  ],

  data() {
    return {
      dependentFieldValues: {},
      isVisible: false,
    };
  },

  computed: {
    containerClasses() {
      if (this.field.applyToFields) {
        return [];
      }

      return [
        'nova-dependency-container',
        'space-y-4',
        'py-4',
        'px-6',
        'bg-gray-50',
        'dark:bg-gray-800',
        'rounded-lg',
        'border',
        'border-gray-200',
        'dark:border-gray-700',
      ];
    },
  },

  mounted() {
    this.watchDependentFields();
    this.checkDependencies();
  },

  methods: {
    watchDependentFields() {
      if (!this.field.dependencies || this.field.dependencies.length === 0) {
        this.isVisible = true;
        return;
      }

      Nova.$on('field-changed', this.handleFieldChanged);
    },

    handleFieldChanged(event) {
      this.dependentFieldValues[event.field.attribute] = event.value;
      this.checkDependencies();
    },

    checkDependencies() {
      if (!this.field.dependencies || this.field.dependencies.length === 0) {
        this.isVisible = true;
        return;
      }

      let satisfied = true;

      for (const dependency of this.field.dependencies) {
        const fieldValue = this.getFieldValue(dependency.field);

        if (dependency.hasOwnProperty('value')) {
          if (Array.isArray(dependency.value)) {
            if (!dependency.value.includes(fieldValue)) {
              satisfied = false;
              break;
            }
          } else if (fieldValue != dependency.value) {
            satisfied = false;
            break;
          }
        } else if (dependency.hasOwnProperty('not')) {
          if (Array.isArray(dependency.not)) {
            if (dependency.not.includes(fieldValue)) {
              satisfied = false;
              break;
            }
          } else if (fieldValue == dependency.not) {
            satisfied = false;
            break;
          }
        } else if (dependency.hasOwnProperty('notIn')) {
          if (dependency.notIn.includes(fieldValue)) {
            satisfied = false;
            break;
          }
        } else if (dependency.hasOwnProperty('empty')) {
          if (dependency.empty && !this.isEmpty(fieldValue)) {
            satisfied = false;
            break;
          }
        } else if (dependency.hasOwnProperty('notEmpty')) {
          if (dependency.notEmpty && this.isEmpty(fieldValue)) {
            satisfied = false;
            break;
          }
        } else if (dependency.hasOwnProperty('nullOrZero')) {
          if (dependency.nullOrZero && fieldValue != null && fieldValue != 0 && fieldValue != '0') {
            satisfied = false;
            break;
          }
        }
      }

      this.isVisible = satisfied;

      if (this.field.applyToFields && this.field.fields) {
        this.field.fields.forEach(field => {
          Nova.$emit('nova-dependency-container:toggle-field', {
            field: field.attribute,
            visible: satisfied,
          });
        });
      }
    },

    getFieldValue(fieldAttribute) {
      if (this.dependentFieldValues.hasOwnProperty(fieldAttribute)) {
        return this.dependentFieldValues[fieldAttribute];
      }

      const field = this.findFieldByAttribute(fieldAttribute);
      if (field) {
        return field.value;
      }

      return null;
    },

    findFieldByAttribute(attribute) {
      const allFields = Nova.$parent?.$refs?.fields || [];
      return allFields.find(f => f.field && f.field.attribute === attribute);
    },

    isEmpty(value) {
      return value === null ||
             value === undefined ||
             value === '' ||
             (Array.isArray(value) && value.length === 0) ||
             (typeof value === 'object' && Object.keys(value).length === 0);
    },

    fill(formData) {
      if (!this.isVisible) {
        return;
      }

      if (this.field.fields) {
        this.field.fields.forEach(field => {
          const fieldComponent = this.$refs[`field-${field.attribute}`];
          if (fieldComponent && typeof fieldComponent.fill === 'function') {
            fieldComponent.fill(formData);
          }
        });
      }
    },
  },

  beforeUnmount() {
    Nova.$off('field-changed', this.handleFieldChanged);
  },
};
</script>

<style lang="scss" scoped>
.nova-dependency-container {
  transition: all 0.3s ease;
}
</style>