import { MatchResult, Template } from 'aws-cdk-lib/assertions';
import * as cdk from 'aws-cdk-lib';
import * as Cdk from '../lib/cdk-stack';

test('Empty Stack', () => {
    const app = new cdk.App();
    // WHEN
    const stack = new Cdk.CdkStack(app, 'MyTestStack');
    // THEN
    const template = Template.fromStack(stack);

        // Testing AWS individual resources within the stack
  // template.templateMatches( {
  //   "Resources": {}
  // },);

  //   template.(matchTemplate({
  //     "Resources": {}
  //   }, MatchStyle.EXACT))
});
