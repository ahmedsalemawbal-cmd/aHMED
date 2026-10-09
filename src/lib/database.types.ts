// Database types from the Supabase type generator for project maidani (helpers trimmed). Regenerate after every migration.

export type Json = string | number | boolean | null | { [key: string]: Json | undefined } | Json[];

export type Database = {
  __InternalSupabase: {
    PostgrestVersion: '14.18';
  };
  public: {
    Tables: {
      activity_types: {
        Row: {
          checklist: Json;
          created_at: string;
          default_service_ids: string[];
          icon: string;
          id: string;
          name: string;
          owner_id: string;
          sort_order: number;
          updated_at: string;
        };
        Insert: {
          checklist?: Json;
          created_at?: string;
          default_service_ids?: string[];
          icon?: string;
          id?: string;
          name: string;
          owner_id?: string;
          sort_order?: number;
          updated_at?: string;
        };
        Update: {
          checklist?: Json;
          created_at?: string;
          default_service_ids?: string[];
          icon?: string;
          id?: string;
          name?: string;
          owner_id?: string;
          sort_order?: number;
          updated_at?: string;
        };
        Relationships: [];
      };
      assessments: {
        Row: {
          answers: Json;
          created_at: string;
          id: string;
          lead_id: string;
          owner_id: string;
          score: number;
          updated_at: string;
          visit_id: string | null;
          weaknesses: Json;
        };
        Insert: {
          answers?: Json;
          created_at?: string;
          id?: string;
          lead_id: string;
          owner_id?: string;
          score: number;
          updated_at?: string;
          visit_id?: string | null;
          weaknesses?: Json;
        };
        Update: {
          answers?: Json;
          created_at?: string;
          id?: string;
          lead_id?: string;
          owner_id?: string;
          score?: number;
          updated_at?: string;
          visit_id?: string | null;
          weaknesses?: Json;
        };
        Relationships: [
          { foreignKeyName: 'assessments_lead_id_fkey'; columns: ['lead_id']; isOneToOne: false; referencedRelation: 'leads'; referencedColumns: ['id'] },
          { foreignKeyName: 'assessments_visit_id_fkey'; columns: ['visit_id']; isOneToOne: false; referencedRelation: 'visits'; referencedColumns: ['id'] },
        ];
      };
      lead_services: {
        Row: {
          created_at: string;
          id: string;
          lead_id: string;
          note: string | null;
          owner_id: string;
          service_id: string;
          status: string;
          updated_at: string;
        };
        Insert: {
          created_at?: string;
          id?: string;
          lead_id: string;
          note?: string | null;
          owner_id?: string;
          service_id: string;
          status?: string;
          updated_at?: string;
        };
        Update: {
          created_at?: string;
          id?: string;
          lead_id?: string;
          note?: string | null;
          owner_id?: string;
          service_id?: string;
          status?: string;
          updated_at?: string;
        };
        Relationships: [
          { foreignKeyName: 'lead_services_lead_id_fkey'; columns: ['lead_id']; isOneToOne: false; referencedRelation: 'leads'; referencedColumns: ['id'] },
          { foreignKeyName: 'lead_services_service_id_fkey'; columns: ['service_id']; isOneToOne: false; referencedRelation: 'services'; referencedColumns: ['id'] },
        ];
      };
      leads: {
        Row: {
          activity_type_id: string | null;
          address: string | null;
          best_contact_time: string | null;
          business_name: string;
          contact_name: string | null;
          contact_role: string | null;
          created_at: string;
          do_not_contact: boolean;
          expected_value: number | null;
          id: string;
          instagram_url: string | null;
          is_decision_maker: boolean;
          last_contact_at: string | null;
          lat: number | null;
          lng: number | null;
          lost_note: string | null;
          lost_reason: string | null;
          maps_url: string | null;
          next_action_at: string | null;
          owner_id: string;
          phone_e164: string | null;
          priority: string | null;
          score: number | null;
          source: string;
          stage: string;
          stage_changed_at: string;
          updated_at: string;
          wa_consent: boolean;
          won_billing: string | null;
          won_value: number | null;
        };
        Insert: {
          activity_type_id?: string | null;
          address?: string | null;
          best_contact_time?: string | null;
          business_name: string;
          contact_name?: string | null;
          contact_role?: string | null;
          created_at?: string;
          do_not_contact?: boolean;
          expected_value?: number | null;
          id?: string;
          instagram_url?: string | null;
          is_decision_maker?: boolean;
          last_contact_at?: string | null;
          lat?: number | null;
          lng?: number | null;
          lost_note?: string | null;
          lost_reason?: string | null;
          maps_url?: string | null;
          next_action_at?: string | null;
          owner_id?: string;
          phone_e164?: string | null;
          priority?: string | null;
          score?: number | null;
          source?: string;
          stage?: string;
          stage_changed_at?: string;
          updated_at?: string;
          wa_consent?: boolean;
          won_billing?: string | null;
          won_value?: number | null;
        };
        Update: {
          activity_type_id?: string | null;
          address?: string | null;
          best_contact_time?: string | null;
          business_name?: string;
          contact_name?: string | null;
          contact_role?: string | null;
          created_at?: string;
          do_not_contact?: boolean;
          expected_value?: number | null;
          id?: string;
          instagram_url?: string | null;
          is_decision_maker?: boolean;
          last_contact_at?: string | null;
          lat?: number | null;
          lng?: number | null;
          lost_note?: string | null;
          lost_reason?: string | null;
          maps_url?: string | null;
          next_action_at?: string | null;
          owner_id?: string;
          phone_e164?: string | null;
          priority?: string | null;
          score?: number | null;
          source?: string;
          stage?: string;
          stage_changed_at?: string;
          updated_at?: string;
          wa_consent?: boolean;
          won_billing?: string | null;
          won_value?: number | null;
        };
        Relationships: [
          { foreignKeyName: 'leads_activity_type_id_fkey'; columns: ['activity_type_id']; isOneToOne: false; referencedRelation: 'activity_types'; referencedColumns: ['id'] },
        ];
      };
      message_templates: {
        Row: {
          activity_type_id: string | null;
          body: string;
          created_at: string;
          id: string;
          kind: string;
          name: string;
          owner_id: string;
          reply_count: number;
          stage: string | null;
          updated_at: string;
          usage_count: number;
        };
        Insert: {
          activity_type_id?: string | null;
          body: string;
          created_at?: string;
          id?: string;
          kind?: string;
          name: string;
          owner_id?: string;
          reply_count?: number;
          stage?: string | null;
          updated_at?: string;
          usage_count?: number;
        };
        Update: {
          activity_type_id?: string | null;
          body?: string;
          created_at?: string;
          id?: string;
          kind?: string;
          name?: string;
          owner_id?: string;
          reply_count?: number;
          stage?: string | null;
          updated_at?: string;
          usage_count?: number;
        };
        Relationships: [
          { foreignKeyName: 'message_templates_activity_type_id_fkey'; columns: ['activity_type_id']; isOneToOne: false; referencedRelation: 'activity_types'; referencedColumns: ['id'] },
        ];
      };
      messages: {
        Row: {
          body: string;
          created_at: string;
          generated_by: string | null;
          id: string;
          kind: string;
          lead_id: string;
          owner_id: string;
          replied_at: string | null;
          sent_at: string | null;
          status: string;
          task_id: string | null;
          template_id: string | null;
          tone: string | null;
          updated_at: string;
        };
        Insert: {
          body: string;
          created_at?: string;
          generated_by?: string | null;
          id?: string;
          kind: string;
          lead_id: string;
          owner_id?: string;
          replied_at?: string | null;
          sent_at?: string | null;
          status?: string;
          task_id?: string | null;
          template_id?: string | null;
          tone?: string | null;
          updated_at?: string;
        };
        Update: {
          body?: string;
          created_at?: string;
          generated_by?: string | null;
          id?: string;
          kind?: string;
          lead_id?: string;
          owner_id?: string;
          replied_at?: string | null;
          sent_at?: string | null;
          status?: string;
          task_id?: string | null;
          template_id?: string | null;
          tone?: string | null;
          updated_at?: string;
        };
        Relationships: [
          { foreignKeyName: 'messages_lead_id_fkey'; columns: ['lead_id']; isOneToOne: false; referencedRelation: 'leads'; referencedColumns: ['id'] },
          { foreignKeyName: 'messages_task_id_fkey'; columns: ['task_id']; isOneToOne: false; referencedRelation: 'tasks'; referencedColumns: ['id'] },
          { foreignKeyName: 'messages_template_id_fkey'; columns: ['template_id']; isOneToOne: false; referencedRelation: 'message_templates'; referencedColumns: ['id'] },
        ];
      };
      profiles: {
        Row: {
          brand_name: string | null;
          created_at: string;
          default_tone: string;
          full_name: string;
          id: string;
          owner_id: string;
          phone: string | null;
          signature: string | null;
          theme: string;
          updated_at: string;
          vat_enabled: boolean;
          vat_rate: number | null;
          weekly_visit_goal: number;
        };
        Insert: {
          brand_name?: string | null;
          created_at?: string;
          default_tone?: string;
          full_name?: string;
          id?: string;
          owner_id?: string;
          phone?: string | null;
          signature?: string | null;
          theme?: string;
          updated_at?: string;
          vat_enabled?: boolean;
          vat_rate?: number | null;
          weekly_visit_goal?: number;
        };
        Update: {
          brand_name?: string | null;
          created_at?: string;
          default_tone?: string;
          full_name?: string;
          id?: string;
          owner_id?: string;
          phone?: string | null;
          signature?: string | null;
          theme?: string;
          updated_at?: string;
          vat_enabled?: boolean;
          vat_rate?: number | null;
          weekly_visit_goal?: number;
        };
        Relationships: [];
      };
      quotes: {
        Row: {
          created_at: string;
          discount_pct: number;
          id: string;
          items: Json;
          lead_id: string;
          monthly_total: number;
          notes: string | null;
          number: string;
          once_total: number;
          owner_id: string;
          sent_at: string | null;
          status: string;
          total: number;
          updated_at: string;
          valid_until: string | null;
          validity_days: number;
          vat_amount: number;
        };
        Insert: {
          created_at?: string;
          discount_pct?: number;
          id?: string;
          items?: Json;
          lead_id: string;
          monthly_total?: number;
          notes?: string | null;
          number: string;
          once_total?: number;
          owner_id?: string;
          sent_at?: string | null;
          status?: string;
          total?: number;
          updated_at?: string;
          valid_until?: string | null;
          validity_days?: number;
          vat_amount?: number;
        };
        Update: {
          created_at?: string;
          discount_pct?: number;
          id?: string;
          items?: Json;
          lead_id?: string;
          monthly_total?: number;
          notes?: string | null;
          number?: string;
          once_total?: number;
          owner_id?: string;
          sent_at?: string | null;
          status?: string;
          total?: number;
          updated_at?: string;
          valid_until?: string | null;
          validity_days?: number;
          vat_amount?: number;
        };
        Relationships: [
          { foreignKeyName: 'quotes_lead_id_fkey'; columns: ['lead_id']; isOneToOne: false; referencedRelation: 'leads'; referencedColumns: ['id'] },
        ];
      };
      services: {
        Row: {
          activity_type_ids: string[];
          billing: string;
          created_at: string;
          description: string | null;
          id: string;
          is_active: boolean;
          name: string;
          owner_id: string;
          price_from: number | null;
          sort_order: number;
          updated_at: string;
          weakness_item_ids: string[];
        };
        Insert: {
          activity_type_ids?: string[];
          billing: string;
          created_at?: string;
          description?: string | null;
          id?: string;
          is_active?: boolean;
          name: string;
          owner_id?: string;
          price_from?: number | null;
          sort_order?: number;
          updated_at?: string;
          weakness_item_ids?: string[];
        };
        Update: {
          activity_type_ids?: string[];
          billing?: string;
          created_at?: string;
          description?: string | null;
          id?: string;
          is_active?: boolean;
          name?: string;
          owner_id?: string;
          price_from?: number | null;
          sort_order?: number;
          updated_at?: string;
          weakness_item_ids?: string[];
        };
        Relationships: [];
      };
      stage_history: {
        Row: {
          changed_at: string;
          created_at: string;
          from_stage: string | null;
          id: string;
          lead_id: string;
          owner_id: string;
          to_stage: string;
          updated_at: string;
        };
        Insert: {
          changed_at?: string;
          created_at?: string;
          from_stage?: string | null;
          id?: string;
          lead_id: string;
          owner_id?: string;
          to_stage: string;
          updated_at?: string;
        };
        Update: {
          changed_at?: string;
          created_at?: string;
          from_stage?: string | null;
          id?: string;
          lead_id?: string;
          owner_id?: string;
          to_stage?: string;
          updated_at?: string;
        };
        Relationships: [
          { foreignKeyName: 'stage_history_lead_id_fkey'; columns: ['lead_id']; isOneToOne: false; referencedRelation: 'leads'; referencedColumns: ['id'] },
        ];
      };
      tasks: {
        Row: {
          cancelled_at: string | null;
          created_at: string;
          done_at: string | null;
          due_at: string;
          id: string;
          kind: string;
          lead_id: string;
          owner_id: string;
          title: string;
          updated_at: string;
        };
        Insert: {
          cancelled_at?: string | null;
          created_at?: string;
          done_at?: string | null;
          due_at: string;
          id?: string;
          kind?: string;
          lead_id: string;
          owner_id?: string;
          title: string;
          updated_at?: string;
        };
        Update: {
          cancelled_at?: string | null;
          created_at?: string;
          done_at?: string | null;
          due_at?: string;
          id?: string;
          kind?: string;
          lead_id?: string;
          owner_id?: string;
          title?: string;
          updated_at?: string;
        };
        Relationships: [
          { foreignKeyName: 'tasks_lead_id_fkey'; columns: ['lead_id']; isOneToOne: false; referencedRelation: 'leads'; referencedColumns: ['id'] },
        ];
      };
      visit_media: {
        Row: {
          created_at: string;
          id: string;
          kind: string;
          owner_id: string;
          storage_path: string;
          updated_at: string;
          visit_id: string;
        };
        Insert: {
          created_at?: string;
          id?: string;
          kind: string;
          owner_id?: string;
          storage_path: string;
          updated_at?: string;
          visit_id: string;
        };
        Update: {
          created_at?: string;
          id?: string;
          kind?: string;
          owner_id?: string;
          storage_path?: string;
          updated_at?: string;
          visit_id?: string;
        };
        Relationships: [
          { foreignKeyName: 'visit_media_visit_id_fkey'; columns: ['visit_id']; isOneToOne: false; referencedRelation: 'visits'; referencedColumns: ['id'] },
        ];
      };
      visits: {
        Row: {
          created_at: string;
          id: string;
          key_observation: string | null;
          lat: number | null;
          lead_id: string;
          lng: number | null;
          next_step: string | null;
          notes: string | null;
          outcome: string | null;
          owner_id: string;
          updated_at: string;
          visited_at: string;
        };
        Insert: {
          created_at?: string;
          id?: string;
          key_observation?: string | null;
          lat?: number | null;
          lead_id: string;
          lng?: number | null;
          next_step?: string | null;
          notes?: string | null;
          outcome?: string | null;
          owner_id?: string;
          updated_at?: string;
          visited_at?: string;
        };
        Update: {
          created_at?: string;
          id?: string;
          key_observation?: string | null;
          lat?: number | null;
          lead_id?: string;
          lng?: number | null;
          next_step?: string | null;
          notes?: string | null;
          outcome?: string | null;
          owner_id?: string;
          updated_at?: string;
          visited_at?: string;
        };
        Relationships: [
          { foreignKeyName: 'visits_lead_id_fkey'; columns: ['lead_id']; isOneToOne: false; referencedRelation: 'leads'; referencedColumns: ['id'] },
        ];
      };
    };
    Views: { [_ in never]: never };
    Functions: {
      assessment_answers_valid: { Args: { a: Json }; Returns: boolean };
      create_lead_from_visit: { Args: { p: Json }; Returns: Json };
      confirm_message_sent: { Args: { p: Json }; Returns: Json };
      undo_message_sent: { Args: { p: Json }; Returns: Json };
      refresh_next_action: { Args: { p_lead: string }; Returns: undefined };
      mark_replied: { Args: { p: Json }; Returns: Json };
      set_meeting: { Args: { p: Json }; Returns: Json };
      mark_won: { Args: { p: Json }; Returns: Json };
      mark_lost: { Args: { p: Json }; Returns: Json };
      set_stage: { Args: { p: Json }; Returns: Json };
    };
    Enums: { [_ in never]: never };
    CompositeTypes: { [_ in never]: never };
  };
};

type PublicSchema = Database['public'];
export type Tables<T extends keyof PublicSchema['Tables']> = PublicSchema['Tables'][T]['Row'];
export type TablesInsert<T extends keyof PublicSchema['Tables']> = PublicSchema['Tables'][T]['Insert'];
export type TablesUpdate<T extends keyof PublicSchema['Tables']> = PublicSchema['Tables'][T]['Update'];
