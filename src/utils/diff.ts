export interface CompareResult {
  compare: {
    fromid: number;
    fromrevid: number;
    fromns: number;
    fromtitle: string;
    toid: number;
    torevid: number;
    tons: number;
    totitle: string;
    '*': string;
  };
}