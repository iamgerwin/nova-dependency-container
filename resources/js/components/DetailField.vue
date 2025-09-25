<template>
  <div v-show="isVisible" :class="containerClasses">
    <component
      v-for="(field, index) in field.fields"
      :key="index"
      :is="`detail-${field.component}`"
      :resource-name="resourceName"
      :resource-id="resourceId"
      :field="field"
    />
  </div>
</template>

<script>
export default {
  props: ['resourceName', 'resourceId', 'field'],

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
    this.checkDependencies();
  },

  methods: {
    checkDependencies() {
      if (!this.field.dependencies || this.field.dependencies.length === 0) {
        this.isVisible = true;
        return;
      }

      let satisfied = true;

      for (const dependency of this.field.dependencies) {
        const field = this.findFieldByAttribute(dependency.field);
        if (!field) {
          satisfied = false;
          break;
        }

        const fieldValue = field.value;

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
    },

    findFieldByAttribute(attribute) {
      const parent = this.$parent;
      if (parent && parent.fields) {
        return parent.fields.find(f => f.attribute === attribute);
      }
      return null;
    },

    isEmpty(value) {
      return value === null ||
             value === undefined ||
             value === '' ||
             (Array.isArray(value) && value.length === 0) ||
             (typeof value === 'object' && Object.keys(value).length === 0);
    },
  },
};
</script>

<style lang="scss" scoped>
.nova-dependency-container {
  transition: all 0.3s ease;
}
</style>