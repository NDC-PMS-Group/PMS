import { defineStore } from "pinia";

interface Profile {
  first_name: string;
  last_name: string;
  email: string;
  middleName: string;
  mobilePhoneNumber: string;
  birthPlace: string;
  sex: string;
  civilStatus: string;
  agencyEmail: string;
  citizenship: string;
  residentialAddressNo: string;
  permanentAddressNo: string;
  birthDate: string;
  [key: string]: any; // Allow additional properties
}

interface ProfileStoreState {
  profile: Profile;
}

export const useProfileStore = defineStore("profile-store", {
  state: (): ProfileStoreState => ({ profile: {} as Profile }),
  actions: {
    setUserProfile(value: Profile): void {
      this.profile = value;
    },
  },
});
